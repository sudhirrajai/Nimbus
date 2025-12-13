<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class SslController extends Controller
{
    private $basePath = '/var/www/';
    private $letsencryptPath = '/etc/letsencrypt/live/';

    /**
     * Display SSL management page
     */
    public function index()
    {
        return Inertia::render('SSL/Index');
    }

    /**
     * Get all domains with their SSL status
     */
    public function getDomains()
    {
        try {
            if (!File::exists($this->basePath)) {
                return response()->json([
                    'error' => "Base path {$this->basePath} does not exist."
                ], 500);
            }

            $directories = collect(File::directories($this->basePath))
                ->map(function ($path) {
                    return basename($path);
                })
                ->filter(function ($name) {
                    return !in_array(strtolower($name), [
                        'html', 'default', 'public', 'cgi-bin', 'nimbus'
                    ]);
                })
                ->map(function ($domain) {
                    return $this->getDomainSslInfo($domain);
                })
                ->values();

            return response()->json([
                'domains' => $directories
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get SSL domains: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load domains: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SSL information for a domain
     */
    private function getDomainSslInfo($domain)
    {
        $certPath = $this->letsencryptPath . $domain . '/fullchain.pem';
        $info = [
            'domain' => $domain,
            'hasSsl' => false,
            'issuer' => null,
            'validFrom' => null,
            'validTo' => null,
            'daysRemaining' => null,
            'status' => 'no_ssl',
            'autoRenew' => false,
        ];

        // Check if certificate exists
        $output = [];
        $returnCode = 0;
        exec("sudo test -f " . escapeshellarg($certPath) . " && echo 'exists'", $output, $returnCode);
        
        if ($returnCode === 0 && isset($output[0]) && $output[0] === 'exists') {
            $info['hasSsl'] = true;
            
            // Get certificate details using openssl
            $certInfo = $this->parseCertificate($certPath);
            if ($certInfo) {
                $info = array_merge($info, $certInfo);
            }
            
            // Check auto-renewal status
            $info['autoRenew'] = $this->checkAutoRenew($domain);
        }

        return $info;
    }

    /**
     * Parse certificate using openssl
     */
    private function parseCertificate($certPath)
    {
        try {
            $escapedPath = escapeshellarg($certPath);
            
            // Get certificate dates
            $output = [];
            exec("sudo openssl x509 -in {$escapedPath} -noout -dates 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                return null;
            }

            $validFrom = null;
            $validTo = null;
            
            foreach ($output as $line) {
                if (strpos($line, 'notBefore=') === 0) {
                    $validFrom = strtotime(str_replace('notBefore=', '', $line));
                }
                if (strpos($line, 'notAfter=') === 0) {
                    $validTo = strtotime(str_replace('notAfter=', '', $line));
                }
            }

            // Get issuer
            $issuerOutput = [];
            exec("sudo openssl x509 -in {$escapedPath} -noout -issuer 2>&1", $issuerOutput);
            $issuer = 'Unknown';
            if (!empty($issuerOutput[0])) {
                // Extract CN from issuer
                if (preg_match('/CN\s*=\s*([^,\/]+)/', $issuerOutput[0], $matches)) {
                    $issuer = trim($matches[1]);
                }
            }

            // Calculate days remaining
            $daysRemaining = null;
            $status = 'valid';
            
            if ($validTo) {
                $daysRemaining = floor(($validTo - time()) / 86400);
                
                if ($daysRemaining < 0) {
                    $status = 'expired';
                } elseif ($daysRemaining <= 30) {
                    $status = 'expiring_soon';
                }
            }

            return [
                'issuer' => $issuer,
                'validFrom' => $validFrom ? date('Y-m-d H:i:s', $validFrom) : null,
                'validTo' => $validTo ? date('Y-m-d H:i:s', $validTo) : null,
                'daysRemaining' => $daysRemaining,
                'status' => $status,
            ];
        } catch (\Exception $e) {
            \Log::error("Failed to parse certificate: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if auto-renewal is enabled for a domain
     */
    private function checkAutoRenew($domain)
    {
        // Check if renewal config exists
        $renewalConf = "/etc/letsencrypt/renewal/{$domain}.conf";
        $output = [];
        exec("sudo test -f " . escapeshellarg($renewalConf) . " && echo 'exists'", $output);
        return isset($output[0]) && $output[0] === 'exists';
    }

    /**
     * Install SSL certificate for a domain
     */
    public function installCertificate(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253'
            ]);

            $domain = strtolower(trim($request->input('domain')));
            
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            // Check if domain directory exists
            $domainPath = $this->basePath . $domain;
            if (!File::exists($domainPath)) {
                return response()->json(['error' => 'Domain directory not found'], 404);
            }

            // Run certbot with nginx plugin
            $output = [];
            $returnCode = 0;
            
            $cmd = "sudo certbot --nginx -d " . escapeshellarg($domain) . " -d " . escapeshellarg("www.{$domain}") . " --non-interactive --agree-tos --register-unsafely-without-email 2>&1";
            
            \Log::info("Running certbot: " . $cmd);
            exec($cmd, $output, $returnCode);
            
            $outputStr = implode("\n", $output);
            \Log::info("Certbot output: " . $outputStr);

            if ($returnCode !== 0) {
                // Check for common errors
                if (strpos($outputStr, 'too many certificates') !== false) {
                    return response()->json([
                        'error' => 'Rate limit reached. Please try again later.',
                        'details' => $outputStr
                    ], 429);
                }
                
                if (strpos($outputStr, 'DNS problem') !== false || strpos($outputStr, 'Could not reach') !== false) {
                    return response()->json([
                        'error' => 'DNS verification failed. Make sure your domain points to this server.',
                        'details' => $outputStr
                    ], 400);
                }

                return response()->json([
                    'error' => 'Failed to install SSL certificate',
                    'details' => $outputStr
                ], 500);
            }

            // Reload nginx to apply changes
            exec("sudo systemctl reload nginx 2>&1");

            return response()->json([
                'message' => "SSL certificate installed successfully for {$domain}",
                'details' => $outputStr
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to install SSL: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew SSL certificate for a domain
     */
    public function renewCertificate(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253'
            ]);

            $domain = strtolower(trim($request->input('domain')));
            
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            // Run certbot renew for specific domain
            $output = [];
            $returnCode = 0;
            
            $cmd = "sudo certbot renew --cert-name " . escapeshellarg($domain) . " --force-renewal 2>&1";
            
            \Log::info("Running certbot renew: " . $cmd);
            exec($cmd, $output, $returnCode);
            
            $outputStr = implode("\n", $output);
            \Log::info("Certbot renew output: " . $outputStr);

            if ($returnCode !== 0) {
                return response()->json([
                    'error' => 'Failed to renew SSL certificate',
                    'details' => $outputStr
                ], 500);
            }

            // Reload nginx
            exec("sudo systemctl reload nginx 2>&1");

            return response()->json([
                'message' => "SSL certificate renewed successfully for {$domain}",
                'details' => $outputStr
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to renew SSL: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew all SSL certificates
     */
    public function renewAll()
    {
        try {
            $output = [];
            $returnCode = 0;
            
            $cmd = "sudo certbot renew 2>&1";
            
            \Log::info("Running certbot renew all");
            exec($cmd, $output, $returnCode);
            
            $outputStr = implode("\n", $output);
            \Log::info("Certbot renew all output: " . $outputStr);

            // Reload nginx
            exec("sudo systemctl reload nginx 2>&1");

            return response()->json([
                'message' => 'All certificates renewal process completed',
                'details' => $outputStr,
                'success' => $returnCode === 0
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to renew all SSL: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove SSL certificate for a domain
     */
    public function removeCertificate(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253'
            ]);

            $domain = strtolower(trim($request->input('domain')));
            
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            // Delete certificate using certbot
            $output = [];
            $returnCode = 0;
            
            $cmd = "sudo certbot delete --cert-name " . escapeshellarg($domain) . " --non-interactive 2>&1";
            
            exec($cmd, $output, $returnCode);
            $outputStr = implode("\n", $output);

            if ($returnCode !== 0) {
                return response()->json([
                    'error' => 'Failed to remove SSL certificate',
                    'details' => $outputStr
                ], 500);
            }

            return response()->json([
                'message' => "SSL certificate removed for {$domain}"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to remove SSL: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate domain name format
     */
    private function isValidDomain($domain)
    {
        return preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $domain) && strlen($domain) <= 253;
    }
}
