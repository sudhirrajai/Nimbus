<?php

namespace App\Services;

use App\Models\DomainCloudflareSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareDnsService
{
    private $basePath = '/var/www/';

    /**
     * Get the main registerable domain from a domain or subdomain name
     */
    public static function getMainDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $parts = explode('.', $domain);
        $count = count($parts);

        if ($count <= 2) {
            return $domain;
        }

        $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];
        $multipartTlds = [
            'co.uk', 'me.uk', 'org.uk', 'net.uk', 'ltd.uk',
            'com.au', 'net.au', 'org.au', 'edu.au', 'gov.au',
            'co.in', 'net.in', 'org.in', 'gen.in', 'firm.in', 'ind.in',
            'com.br', 'net.br', 'org.br', 'co.nz', 'net.nz', 'org.nz',
            'com.sg', 'net.sg', 'org.sg', 'com.tw', 'net.tw', 'org.tw',
            'co.za', 'net.za', 'org.za', 'com.mx', 'net.mx', 'org.mx'
        ];

        if (in_array($lastTwo, $multipartTlds)) {
            if ($count == 3) {
                return $domain;
            }
            return $parts[$count - 3] . '.' . $lastTwo;
        }

        return $parts[$count - 2] . '.' . $parts[$count - 1];
    }

    /**
     * Determine if a domain is a subdomain whose parent domain is hosted on this Nimbus server
     */
    public function findParentDomain(string $subdomain): ?string
    {
        $subdomain = strtolower(trim($subdomain));

        // 1. Check using multi-part aware registerable domain helper
        $mainCandidate = self::getMainDomain($subdomain);
        if ($mainCandidate !== $subdomain) {
            // Check if parent domain directory exists on this server or has Cloudflare configured
            if (File::exists($this->basePath . $mainCandidate) || 
                DomainCloudflareSetting::whereRaw('LOWER(domain) = ?', [$mainCandidate])->exists()) {
                return $mainCandidate;
            }
        }

        // 2. Check all managed website directories in /var/www/
        if (File::exists($this->basePath)) {
            $directories = collect(File::directories($this->basePath))
                ->map(fn($p) => strtolower(basename($p)))
                ->filter(fn($d) => !in_array($d, ['html', 'default', 'public', 'cgi-bin', 'nimbus', '000-nimbus', 'nimbus_panel']));

            foreach ($directories as $managed) {
                if ($subdomain !== $managed && str_ends_with($subdomain, '.' . $managed)) {
                    return $managed;
                }
            }
        }

        // 3. Check any domain connected in Cloudflare settings
        $allSettings = DomainCloudflareSetting::all();
        foreach ($allSettings as $setting) {
            $parent = strtolower(trim($setting->domain));
            if ($subdomain !== $parent && str_ends_with($subdomain, '.' . $parent)) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Get Cloudflare credentials for a domain if connected
     */
    public function getCloudflareSettingForDomain(string $domain): ?DomainCloudflareSetting
    {
        $domain = strtolower(trim($domain));
        return DomainCloudflareSetting::whereRaw('LOWER(domain) = ?', [$domain])->first();
    }

    /**
     * Automatically create an A record in Cloudflare when a subdomain is created,
     * pointing to the Nimbus server IP.
     *
     * @param string $subdomain
     * @param string|null $serverIp
     * @return array
     */
    public function autoCreateSubdomainRecord(string $subdomain, ?string $serverIp = null): array
    {
        $subdomain = strtolower(trim($subdomain));

        try {
            // 1. Detect parent domain
            $parentDomain = $this->findParentDomain($subdomain);
            if (!$parentDomain) {
                return [
                    'synced' => false,
                    'reason' => 'not_subdomain_or_parent_not_on_server',
                    'message' => 'Not a subdomain of any managed domain on this server.'
                ];
            }

            // 2. Check if parent domain has Cloudflare configured
            $setting = $this->getCloudflareSettingForDomain($parentDomain);
            if (!$setting || empty($setting->api_token) || empty($setting->zone_id)) {
                return [
                    'synced' => false,
                    'reason' => 'parent_not_connected_to_cloudflare',
                    'parent_domain' => $parentDomain,
                    'message' => "Parent domain {$parentDomain} is not connected to Cloudflare."
                ];
            }

            // 3. Resolve server IP if not provided
            if (!$serverIp) {
                $serverIp = $this->getServerIp();
            }

            if (!filter_var($serverIp, FILTER_VALIDATE_IP)) {
                return [
                    'synced' => false,
                    'reason' => 'invalid_server_ip',
                    'parent_domain' => $parentDomain,
                    'message' => 'Unable to determine server public IP address.'
                ];
            }

            // 4. Check if A record already exists in Cloudflare
            $searchResponse = Http::withToken($setting->api_token)
                ->timeout(12)
                ->get("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records", [
                    'type' => 'A',
                    'name' => $subdomain
                ]);

            if ($searchResponse->successful()) {
                $existingRecords = $searchResponse->json('result', []);
                if (!empty($existingRecords)) {
                    $existing = $existingRecords[0];
                    if ($existing['content'] === $serverIp) {
                        Log::info("[CloudflareAutoDNS] Subdomain {$subdomain} A record already points to {$serverIp}");
                        return [
                            'synced' => true,
                            'action' => 'already_exists',
                            'parent_domain' => $parentDomain,
                            'ip' => $serverIp,
                            'message' => "Cloudflare A record for {$subdomain} already points to {$serverIp}."
                        ];
                    }

                    // Update existing record if it points to a different IP
                    $updateResponse = Http::withToken($setting->api_token)
                        ->timeout(12)
                        ->put("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records/{$existing['id']}", [
                            'type' => 'A',
                            'name' => $subdomain,
                            'content' => $serverIp,
                            'ttl' => 1,
                            'proxied' => false,
                            'comment' => 'Auto-updated by Nimbus for subdomain'
                        ]);

                    if ($updateResponse->successful()) {
                        Log::info("[CloudflareAutoDNS] Updated Cloudflare A record for {$subdomain} -> {$serverIp}");
                        return [
                            'synced' => true,
                            'action' => 'updated',
                            'parent_domain' => $parentDomain,
                            'ip' => $serverIp,
                            'message' => "Cloudflare A record for {$subdomain} updated to {$serverIp}."
                        ];
                    }
                }
            }

            // 5. Create new A record
            $createResponse = Http::withToken($setting->api_token)
                ->timeout(12)
                ->post("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records", [
                    'type' => 'A',
                    'name' => $subdomain,
                    'content' => $serverIp,
                    'ttl' => 1,
                    'proxied' => false,
                    'comment' => 'Auto-created by Nimbus for subdomain'
                ]);

            if ($createResponse->successful()) {
                Log::info("[CloudflareAutoDNS] Auto-created Cloudflare A record for subdomain {$subdomain} -> {$serverIp} under parent {$parentDomain}");
                return [
                    'synced' => true,
                    'action' => 'created',
                    'parent_domain' => $parentDomain,
                    'ip' => $serverIp,
                    'message' => "Cloudflare A record for {$subdomain} automatically created pointing to {$serverIp}."
                ];
            }

            $errMsg = $createResponse->json('errors.0.message', 'Unknown Cloudflare API error');
            Log::warning("[CloudflareAutoDNS] Cloudflare API error creating record for {$subdomain}: {$errMsg}");
            return [
                'synced' => false,
                'reason' => 'api_error',
                'parent_domain' => $parentDomain,
                'error' => $errMsg,
                'message' => "Cloudflare API error: {$errMsg}"
            ];

        } catch (\Throwable $e) {
            Log::error("[CloudflareAutoDNS] Exception while creating record for {$subdomain}: " . $e->getMessage());
            return [
                'synced' => false,
                'reason' => 'exception',
                'error' => $e->getMessage(),
                'message' => 'Failed to connect to Cloudflare: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clean up Cloudflare DNS record if a subdomain is deleted
     */
    public function autoDeleteSubdomainRecord(string $subdomain): array
    {
        $subdomain = strtolower(trim($subdomain));

        try {
            $parentDomain = $this->findParentDomain($subdomain);
            if (!$parentDomain) {
                return ['deleted' => false, 'reason' => 'not_subdomain'];
            }

            $setting = $this->getCloudflareSettingForDomain($parentDomain);
            if (!$setting || empty($setting->api_token) || empty($setting->zone_id)) {
                return ['deleted' => false, 'reason' => 'not_connected'];
            }

            // Find existing A record
            $search = Http::withToken($setting->api_token)
                ->timeout(10)
                ->get("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records", [
                    'type' => 'A',
                    'name' => $subdomain
                ]);

            if ($search->successful()) {
                $records = $search->json('result', []);
                foreach ($records as $rec) {
                    Http::withToken($setting->api_token)
                        ->timeout(10)
                        ->delete("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records/{$rec['id']}");
                    Log::info("[CloudflareAutoDNS] Deleted DNS record {$rec['id']} for {$subdomain} from Cloudflare");
                }
                return ['deleted' => true, 'count' => count($records)];
            }

            return ['deleted' => false];
        } catch (\Throwable $e) {
            Log::warning("[CloudflareAutoDNS] Exception deleting record for {$subdomain}: " . $e->getMessage());
            return ['deleted' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get server's public IP address
     */
    public function getServerIp(): string
    {
        return cache()->remember('server_public_ip', 3600, function () {
            try {
                $services = [
                    'https://api.ipify.org',
                    'https://icanhazip.com',
                    'https://ifconfig.me/ip'
                ];

                foreach ($services as $service) {
                    $ip = @file_get_contents($service);
                    if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                        return trim($ip);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch public IP: " . $e->getMessage());
            }

            return request()->server('SERVER_ADDR') ?: '127.0.0.1';
        });
    }
}
