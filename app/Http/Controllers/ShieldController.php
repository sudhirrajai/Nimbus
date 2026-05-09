<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SecurityThreat;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ShieldController extends Controller
{
    /**
     * Display Nimbus Shield dashboard
     */
    public function index()
    {
        return Inertia::render('Shield/Index');
    }

    /**
     * Get security status and recent threats
     */
    public function getStatus()
    {
        try {
            $threats = SecurityThreat::where('status', '!=', 'deleted')
                ->orderBy('detected_at', 'desc')
                ->limit(50)
                ->get();

            $stats = [
                'active_threats' => SecurityThreat::where('status', 'detected')->count(),
                'quarantined' => SecurityThreat::where('status', 'quarantined')->count(),
                'last_scan' => SecurityThreat::max('detected_at')?->diffForHumans() ?? 'Never',
                'firewall_status' => $this->getFirewallStatus(),
                'scan_status' => Setting::where('key', 'shield_scan_status')->value('value') ?: 'idle',
            ];

            return response()->json([
                'success' => true,
                'threats' => $threats,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Perform a security scan
     */
    public function startScan(Request $request)
    {
        // Check if scan is already running
        $currentStatus = Setting::where('key', 'shield_scan_status')->value('value');
        if ($currentStatus === 'running') {
            return response()->json(['error' => 'A scan is already in progress'], 409);
        }

        $path = $request->input('path', '/var/www');
        
        // Ensure path is safe
        if (!str_starts_with($path, '/var/www') && !str_starts_with($path, '/usr/local/nimbus')) {
             return response()->json(['error' => 'Invalid scan path'], 403);
        }

        // Set status to running
        Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'running']);

        // Allow the script to continue after disconnect
        ignore_user_abort(true);
        set_time_limit(0);

        try {
            $findings = [];
            
            // 1. Scan for long hex-named HTML files (SEO injections)
            // Use find -type f for efficiency
            $hexFiles = $this->executeSudoCommand("find " . escapeshellarg($path) . " -type f -regex '.*/[0-9a-f]\{10,20\}\.html'");
            foreach ($hexFiles as $file) {
                if (empty($file)) continue;
                $findings[] = [
                    'file_path' => $file,
                    'type' => 'Suspicious HTML (Hex-named)',
                    'details' => 'Likely SEO injection or backdoor.'
                ];
            }

            // 2. Scan for common PHP shell patterns
            $shellPatterns = ['eval(base64_decode', 'shell_exec(', 'passthru(', 'system(', 'gzuncompress(base64_decode'];
            foreach ($shellPatterns as $pattern) {
                $cmd = "grep -rl " . escapeshellarg($pattern) . " " . escapeshellarg($path) . " --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=storage 2>/dev/null";
                $files = $this->executeSudoCommand($cmd);
                foreach ($files as $file) {
                    if (empty($file) || !is_string($file)) continue;
                    $findings[] = [
                        'file_path' => $file,
                        'type' => 'Potential Web Shell',
                        'details' => "Contains suspicious function: $pattern"
                    ];
                }
            }

            // Save findings to database
            foreach ($findings as $finding) {
                SecurityThreat::updateOrCreate(
                    ['file_path' => $finding['file_path']],
                    [
                        'type' => $finding['type'],
                        'details' => $finding['details'],
                        'status' => 'detected',
                        'detected_at' => now()
                    ]
                );
            }

            // Set status to idle
            Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'idle']);

            return response()->json([
                'success' => true,
                'message' => 'Scan completed',
                'findings_count' => count($findings)
            ]);
        } catch (\Exception $e) {
            \Log::error("Shield scan failed: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        } finally {
            Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'idle']);
        }
    }

    /**
     * Force stop/reset scan status
     */
    public function stopScan()
    {
        Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'idle']);
        return response()->json(['success' => true, 'message' => 'Scan status reset']);
    }

    /**
     * Quarantine a threat
     */
    public function quarantine(Request $request)
    {
        $id = $request->input('id');
        $threat = SecurityThreat::find($id);
        
        if (!$threat) return response()->json(['error' => 'Threat not found'], 404);

        $source = $threat->file_path;
        $quarantineDir = storage_path('app/quarantine');
        
        if (!is_dir($quarantineDir)) {
            mkdir($quarantineDir, 0700, true);
        }

        $filename = basename($source) . '.' . time() . '.bak';
        $destination = $quarantineDir . '/' . $filename;

        try {
            $this->executeSudoCommand("mv " . escapeshellarg($source) . " " . escapeshellarg($destination));
            $this->executeSudoCommand("chmod 000 " . escapeshellarg($destination)); // Make it unreadable

            $threat->update([
                'status' => 'quarantined',
                'details' => $threat->details . " | Quarantined to: $destination",
                'resolved_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'File quarantined successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a threat permanently
     */
    public function deleteThreat(Request $request)
    {
        $id = $request->input('id');
        $threat = SecurityThreat::find($id);
        
        if (!$threat) return response()->json(['error' => 'Threat not found'], 404);

        try {
            if (File::exists($threat->file_path)) {
                $this->executeSudoCommand("rm " . escapeshellarg($threat->file_path));
            }

            $threat->update([
                'status' => 'deleted',
                'resolved_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Threat deleted permanently']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getFirewallStatus()
    {
        exec("sudo ufw status", $output);
        $statusLine = $output[0] ?? '';
        return str_contains($statusLine, 'active') && !str_contains($statusLine, 'inactive') ? 'Active' : 'Inactive';
    }

    /**
     * Scan a single file for threats
     * Returns threat details or null if clean
     */
    public static function scanFile($filePath)
    {
        if (!file_exists($filePath)) return null;

        $filename = basename($filePath);
        
        // 1. Check for hex-named HTML files
        if (preg_match('/^[0-9a-f]{10,20}\.html$/', $filename)) {
            return [
                'type' => 'Suspicious HTML (Hex-named)',
                'details' => 'Likely SEO injection or backdoor.'
            ];
        }

        // 2. Check for shell patterns
        $shellPatterns = ['eval(base64_decode', 'shell_exec(', 'passthru(', 'system(', 'gzuncompress(base64_decode'];
        $content = file_get_contents($filePath);
        foreach ($shellPatterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return [
                    'type' => 'Potential Web Shell',
                    'details' => "Contains suspicious function: $pattern"
                ];
            }
        }

        return null;
    }

    private function executeSudoCommand($command)
    {
        $output = [];
        $returnCode = 0;
        exec("sudo $command 2>&1", $output, $returnCode);
        return $output;
    }
}
