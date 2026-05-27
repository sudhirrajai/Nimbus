<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DomainCloudflareSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class CloudflareDnsController extends Controller
{
    private $basePath = '/var/www/';

    public function index()
    {
        return Inertia::render('DNS/Index');
    }

    public function getDomains()
    {
        try {
            $user = auth()->user();
            $accessibleDomains = $user->accessibleDomains();

            $directories = collect(File::directories($this->basePath))
                ->map(function ($path) {
                    return basename($path);
                })
                ->filter(function ($domain) use ($user, $accessibleDomains) {
                    if (in_array(strtolower($domain), ['html', 'default', 'public', 'cgi-bin', 'nimbus'])) {
                        return false;
                    }
                    if (!$user->isRoot()) {
                        return in_array($domain, $accessibleDomains);
                    }
                    return true;
                })
                ->map(function ($domain) {
                    return self::getMainDomain($domain);
                })
                ->unique()
                ->map(function ($domain) {
                    $setting = DomainCloudflareSetting::where('domain', $domain)->first();
                    return [
                        'domain' => $domain,
                        'is_connected' => $setting !== null,
                        'zone_id' => $setting ? $setting->zone_id : null,
                    ];
                })
                ->values();

            return response()->json([
                'domains' => $directories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load domains: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the main registerable domain from a domain name
     */
    private static function getMainDomain($domain)
    {
        $domain = strtolower($domain);
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

    public function saveCredentials(Request $request, $domain)
    {
        try {
            $request->validate([
                'api_token' => 'required|string',
                'zone_id' => 'required|string'
            ]);

            // Security check
            $user = auth()->user();
            if (!$user->isRoot() && !in_array($domain, $user->accessibleDomains())) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Verify with Cloudflare API
            $response = Http::withToken($request->api_token)
                ->get("https://api.cloudflare.com/client/v4/zones/{$request->zone_id}");

            if (!$response->successful()) {
                return response()->json(['error' => 'Invalid Cloudflare API Token or Zone ID'], 400);
            }

            DomainCloudflareSetting::updateOrCreate(
                ['domain' => $domain],
                [
                    'api_token' => $request->api_token,
                    'zone_id' => $request->zone_id
                ]
            );

            return response()->json([
                'message' => 'Cloudflare credentials saved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getCloudflareSetting($domain)
    {
        $user = auth()->user();
        if (!$user->isRoot() && !in_array($domain, $user->accessibleDomains())) {
            throw new \Exception('Unauthorized access to domain');
        }

        $setting = DomainCloudflareSetting::where('domain', $domain)->first();
        if (!$setting) {
            throw new \Exception('Cloudflare not configured for this domain');
        }

        return $setting;
    }

    public function getRecords($domain)
    {
        try {
            $setting = $this->getCloudflareSetting($domain);

            $response = Http::withToken($setting->api_token)
                ->get("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records?per_page=100");

            if (!$response->successful()) {
                throw new \Exception($response->json('errors.0.message', 'Failed to fetch DNS records'));
            }

            return response()->json([
                'records' => $response->json('result')
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createRecord(Request $request, $domain)
    {
        try {
            $request->validate([
                'type' => 'required|string',
                'name' => 'required|string',
                'content' => 'required|string',
                'ttl' => 'required|integer',
                'proxied' => 'boolean',
                'priority' => 'nullable|integer'
            ]);

            $setting = $this->getCloudflareSetting($domain);

            $response = Http::withToken($setting->api_token)
                ->post("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records", $request->only(['type', 'name', 'content', 'ttl', 'proxied', 'priority']));

            if (!$response->successful()) {
                throw new \Exception($response->json('errors.0.message', 'Failed to create DNS record'));
            }

            return response()->json([
                'message' => 'Record created successfully',
                'record' => $response->json('result')
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateRecord(Request $request, $domain, $recordId)
    {
        try {
            $request->validate([
                'type' => 'required|string',
                'name' => 'required|string',
                'content' => 'required|string',
                'ttl' => 'required|integer',
                'proxied' => 'boolean',
                'priority' => 'nullable|integer'
            ]);

            $setting = $this->getCloudflareSetting($domain);

            $response = Http::withToken($setting->api_token)
                ->put("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records/{$recordId}", $request->only(['type', 'name', 'content', 'ttl', 'proxied', 'priority']));

            if (!$response->successful()) {
                throw new \Exception($response->json('errors.0.message', 'Failed to update DNS record'));
            }

            return response()->json([
                'message' => 'Record updated successfully',
                'record' => $response->json('result')
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteRecord($domain, $recordId)
    {
        try {
            $setting = $this->getCloudflareSetting($domain);

            $response = Http::withToken($setting->api_token)
                ->delete("https://api.cloudflare.com/client/v4/zones/{$setting->zone_id}/dns_records/{$recordId}");

            if (!$response->successful()) {
                throw new \Exception($response->json('errors.0.message', 'Failed to delete DNS record'));
            }

            return response()->json([
                'message' => 'Record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
