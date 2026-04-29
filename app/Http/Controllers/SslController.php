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
                'domains' => $directories,
                'certbotInstalled' => $this->isCertbotInstalled(),
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
        $info = [
            'domain' => $domain,
            'hasSsl' => false,
            'issuer' => null,
            'validFrom' => null,
            'validTo' => null,
            'daysRemaining' => null,
            'status' => 'no_ssl',
            'autoRenew' => false,
            'sslSource' => null, // 'letsencrypt', 'nginx_custom', or null
        ];

        // Strategy 1: Check Let's Encrypt certificate path
        $leCertPath = $this->letsencryptPath . $domain . '/fullchain.pem';
        $certPath = $this->findCertificatePath($domain, $leCertPath);

        if ($certPath) {
            $info['hasSsl'] = true;
            $info['sslSource'] = ($certPath === $leCertPath) ? 'letsencrypt' : 'nginx_custom';

            // Get certificate details using openssl
            $certInfo = $this->parseCertificate($certPath);
            if ($certInfo) {
                $info = array_merge($info, $certInfo);
            }

            // Check auto-renewal status (only relevant for Let's Encrypt)
            if ($info['sslSource'] === 'letsencrypt') {
                $info['autoRenew'] = $this->checkAutoRenew($domain);
            }

            return $info;
        }

        // Strategy 2: Check Nginx config for ssl_certificate directive
        $nginxSslInfo = $this->checkNginxSslConfig($domain);
        if ($nginxSslInfo) {
            $info['hasSsl'] = true;
            $info['sslSource'] = 'nginx_custom';

            if ($nginxSslInfo['certPath']) {
                $certInfo = $this->parseCertificate($nginxSslInfo['certPath']);
                if ($certInfo) {
                    $info = array_merge($info, $certInfo);
                }
            }
        }

        // Strategy 3: Check if domain responds on HTTPS using openssl s_client
        if (!$info['hasSsl']) {
            $liveInfo = $this->checkLiveSsl($domain);
            if ($liveInfo) {
                $info['hasSsl'] = true;
                $info['sslSource'] = 'detected_live';
                $info = array_merge($info, $liveInfo);
            }
        }

        return $info;
    }

    /**
     * Find the certificate file path - checks Let's Encrypt first, then common locations
     */
    private function findCertificatePath($domain, $leCertPath)
    {
        // Check Let's Encrypt path
        $output = [];
        $returnCode = 0;
        exec("sudo test -f " . escapeshellarg($leCertPath) . " && echo 'exists'", $output, $returnCode);

        if ($returnCode === 0 && isset($output[0]) && $output[0] === 'exists') {
            return $leCertPath;
        }

        // Check common alternative paths
        $alternativePaths = [
            "/etc/ssl/certs/{$domain}.pem",
            "/etc/ssl/certs/{$domain}.crt",
            "/etc/nginx/ssl/{$domain}.pem",
            "/etc/nginx/ssl/{$domain}.crt",
            "/etc/nginx/ssl/{$domain}/fullchain.pem",
            "/etc/pki/tls/certs/{$domain}.pem",
        ];

        foreach ($alternativePaths as $path) {
            $output = [];
            exec("sudo test -f " . escapeshellarg($path) . " && echo 'exists'", $output);
            if (isset($output[0]) && $output[0] === 'exists') {
                return $path;
            }
        }

        return null;
    }

    /**
     * Check Nginx config for SSL certificate directives
     */
    private function checkNginxSslConfig($domain)
    {
        $configPaths = [
            "/etc/nginx/sites-available/{$domain}",
            "/etc/nginx/sites-enabled/{$domain}",
        ];

        foreach ($configPaths as $configPath) {
            $output = [];
            exec("sudo test -f " . escapeshellarg($configPath) . " && echo 'exists'", $output);

            if (!isset($output[0]) || $output[0] !== 'exists') {
                continue;
            }

            // Read the Nginx config and look for SSL directives
            $configOutput = [];
            exec("sudo cat " . escapeshellarg($configPath), $configOutput);
            $configContent = implode("\n", $configOutput);

            // Check for listen 443 ssl or ssl_certificate
            $hasSSL = preg_match('/listen\s+.*443\s+ssl/i', $configContent)
                || preg_match('/ssl_certificate\s+/i', $configContent);

            if ($hasSSL) {
                $certPath = null;
                // Extract ssl_certificate path
                if (preg_match('/ssl_certificate\s+([^;]+);/i', $configContent, $matches)) {
                    $certPath = trim($matches[1]);
                    // Remove quotes if present
                    $certPath = trim($certPath, "'\"");
                }

                return [
                    'hasSSL' => true,
                    'certPath' => $certPath,
                ];
            }
        }

        return null;
    }

    private function checkLiveSsl($domain)
    {
        try {
            $streamContext = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'SNI_enabled' => true,
                    'peer_name' => $domain
                ]
            ]);

            $client = @stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $streamContext);
            
            if (!$client) {
                return null;
            }

            $params = stream_context_get_params($client);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;
            
            if (!$cert) {
                return null;
            }

            $certInfo = openssl_x509_parse($cert);
            if (!$certInfo) {
                return null;
            }

            // Verify the certificate actually covers this domain
            $validForDomain = false;
            
            // Check Common Name (CN)
            $cn = $certInfo['subject']['CN'] ?? '';
            if ($this->domainMatchesCert($domain, $cn)) {
                $validForDomain = true;
            }

            // Check Subject Alternative Names (SAN)
            if (!$validForDomain && isset($certInfo['extensions']['subjectAltName'])) {
                $sans = explode(',', $certInfo['extensions']['subjectAltName']);
                foreach ($sans as $san) {
                    $san = trim(str_replace('DNS:', '', $san));
                    if ($this->domainMatchesCert($domain, $san)) {
                        $validForDomain = true;
                        break;
                    }
                }
            }

            // If the certificate doesn't cover this domain, ignore it (it's likely a server fallback cert)
            if (!$validForDomain) {
                return null;
            }

            $validFrom = $certInfo['validFrom_time_t'] ?? null;
            $validTo = $certInfo['validTo_time_t'] ?? null;
            
            // Extract Issuer Organization or CN
            $issuer = $certInfo['issuer']['O'] ?? $certInfo['issuer']['CN'] ?? 'Unknown';

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
            \Log::warning("Live SSL check failed for {$domain}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a domain matches a certificate name (handling wildcards)
     */
    private function domainMatchesCert($domain, $certName)
    {
        $domain = strtolower(trim($domain));
        $certName = strtolower(trim($certName));

        if ($domain === $certName) {
            return true;
        }

        // Handle wildcards (e.g., *.example.com matches test.example.com)
        if (strpos($certName, '*.') === 0) {
            $baseDomain = substr($certName, 2);
            $parts = explode('.', $domain);
            if (count($parts) > 1) {
                array_shift($parts); // Remove first part (subdomain)
                $domainWithoutSub = implode('.', $parts);
                return $domainWithoutSub === $baseDomain;
            }
        }

        return false;
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
     * Check if certbot is installed and return its path
     */
    private function getCertbotPath()
    {
        // Check common locations in priority order
        $paths = [
            '/usr/bin/certbot',
            '/usr/local/bin/certbot',
            '/snap/bin/certbot',
        ];

        foreach ($paths as $path) {
            $output = [];
            exec("test -x " . escapeshellarg($path) . " && echo 'exists'", $output);
            if (isset($output[0]) && $output[0] === 'exists') {
                return $path;
            }
        }

        // Fallback: try which
        $output = [];
        exec("which certbot 2>/dev/null", $output);
        if (!empty($output[0]) && trim($output[0]) !== '') {
            return trim($output[0]);
        }

        // Fallback: try sudo which (different PATH)
        $output = [];
        exec("sudo which certbot 2>/dev/null", $output);
        if (!empty($output[0]) && trim($output[0]) !== '') {
            return trim($output[0]);
        }

        return null;
    }

    /**
     * Check if certbot is installed
     */
    private function isCertbotInstalled()
    {
        return $this->getCertbotPath() !== null;
    }

    /**
     * Attempt to install certbot automatically
     */
    private function installCertbot()
    {
        $output = [];
        $returnCode = 0;

        // Try apt-based install (Debian/Ubuntu)
        \Log::info("Attempting to install certbot via apt...");
        exec("sudo apt-get update -qq 2>&1 && sudo apt-get install -y -qq certbot python3-certbot-nginx 2>&1", $output, $returnCode);

        if ($returnCode === 0 && $this->isCertbotInstalled()) {
            \Log::info("Certbot installed successfully via apt");
            return true;
        }

        // Try snap-based install as fallback
        \Log::info("apt install failed, trying snap...");
        $output = [];
        exec("sudo snap install --classic certbot 2>&1 && sudo ln -sf /snap/bin/certbot /usr/bin/certbot 2>&1", $output, $returnCode);

        if ($returnCode === 0 && $this->isCertbotInstalled()) {
            \Log::info("Certbot installed successfully via snap");
            return true;
        }

        \Log::error("Failed to install certbot. Output: " . implode("\n", $output));
        return false;
    }

    /**
     * Ensure certbot is available, installing it if necessary.
     * Returns the certbot path on success, or throws an exception.
     */
    private function ensureCertbot()
    {
        $certbotPath = $this->getCertbotPath();
        if ($certbotPath) {
            return $certbotPath;
        }

        // Attempt auto-install
        \Log::info("Certbot not found, attempting automatic installation...");
        if ($this->installCertbot()) {
            $certbotPath = $this->getCertbotPath();
            if ($certbotPath) {
                return $certbotPath;
            }
        }

        throw new \Exception(
            "Certbot is not installed and automatic installation failed. "
            . "Please install it manually:\n"
            . "  sudo apt-get update && sudo apt-get install -y certbot python3-certbot-nginx\n"
            . "Or via snap:\n"
            . "  sudo snap install --classic certbot && sudo ln -s /snap/bin/certbot /usr/bin/certbot"
        );
    }

    /**
     * Get certbot installation status
     */
    public function certbotStatus()
    {
        try {
            $certbotPath = $this->getCertbotPath();
            $installed = $certbotPath !== null;
            $version = null;

            if ($installed) {
                $output = [];
                exec("{$certbotPath} --version 2>&1", $output);
                $version = !empty($output) ? implode(' ', $output) : 'Unknown version';
            }

            return response()->json([
                'installed' => $installed,
                'path' => $certbotPath,
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'installed' => false,
                'path' => null,
                'version' => null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Install certbot on the system
     */
    public function installCertbotAction()
    {
        try {
            if ($this->isCertbotInstalled()) {
                return response()->json([
                    'message' => 'Certbot is already installed',
                    'path' => $this->getCertbotPath(),
                ]);
            }

            if ($this->installCertbot()) {
                return response()->json([
                    'message' => 'Certbot installed successfully',
                    'path' => $this->getCertbotPath(),
                ]);
            }

            return response()->json([
                'error' => 'Failed to install Certbot. Please install manually: sudo apt-get install -y certbot python3-certbot-nginx',
            ], 500);
        } catch (\Exception $e) {
            \Log::error("Failed to install certbot: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
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

            // Ensure certbot is available (auto-install if needed)
            $certbotPath = $this->ensureCertbot();

            // Run certbot with nginx plugin
            $output = [];
            $returnCode = 0;

            $cmd = "sudo {$certbotPath} --nginx -d " . escapeshellarg($domain) . " -d " . escapeshellarg("www.{$domain}") . " --non-interactive --agree-tos --register-unsafely-without-email 2>&1";

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

                if (strpos($outputStr, 'not found') !== false || strpos($outputStr, 'No module named') !== false) {
                    return response()->json([
                        'error' => 'Certbot plugin error. Try reinstalling: sudo apt-get install --reinstall python3-certbot-nginx',
                        'details' => $outputStr
                    ], 500);
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

            // Ensure certbot is available
            $certbotPath = $this->ensureCertbot();

            // Run certbot renew for specific domain
            $output = [];
            $returnCode = 0;

            $cmd = "sudo {$certbotPath} renew --cert-name " . escapeshellarg($domain) . " --force-renewal 2>&1";

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
            // Ensure certbot is available
            $certbotPath = $this->ensureCertbot();

            $output = [];
            $returnCode = 0;

            $cmd = "sudo {$certbotPath} renew 2>&1";

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

            // Ensure certbot is available
            $certbotPath = $this->ensureCertbot();

            // Delete certificate using certbot
            $output = [];
            $returnCode = 0;

            $cmd = "sudo {$certbotPath} delete --cert-name " . escapeshellarg($domain) . " --non-interactive 2>&1";

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
