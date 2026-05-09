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

            $lastScan = SecurityThreat::max('detected_at');
            $stats = [
                'active_threats' => SecurityThreat::where('status', 'detected')->count(),
                'quarantined' => SecurityThreat::where('status', 'quarantined')->count(),
                'last_scan' => $lastScan ? \Illuminate\Support\Carbon::parse($lastScan)->diffForHumans() : 'Never',
                'firewall_status' => $this->getFirewallStatus(),
                'scan_status' => 'idle'
            ];

            try {
                $stats['scan_status'] = Setting::where('key', 'shield_scan_status')->value('value') ?: 'idle';
            } catch (\Exception $e) {
                // Settings table might not exist yet
                \Log::warning("Settings table check failed: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'threats' => $threats,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get Shield status: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform a security scan
     */
    public function startScan(Request $request)
    {
        try {
            // Check if scan is already running
            $currentStatus = 'idle';
            try {
                $currentStatus = Setting::where('key', 'shield_scan_status')->value('value') ?: 'idle';
            } catch (\Exception $e) {
                \Log::warning("Settings table check failed during startScan: " . $e->getMessage());
            }

            if ($currentStatus === 'running') {
                return response()->json(['error' => 'A scan is already in progress'], 409);
            }

            $path = $request->input('path', '/var/www');
            
            // Ensure path is safe
            if (!str_starts_with($path, '/var/www') && !str_starts_with($path, '/usr/local/nimbus')) {
                 return response()->json(['error' => 'Invalid scan path'], 403);
            }

            // Set status to running
            try {
                Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'running']);
            } catch (\Exception $e) {
                \Log::warning("Could not update scan status: " . $e->getMessage());
            }

            // Allow the script to continue after disconnect
            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }

            try {
                $findings = [];
                
                // 1. Scan for long hex-named HTML files (SEO injections)
                // Use find -type f for efficiency
                $hexFiles = $this->executeSudoCommand("find " . escapeshellarg($path) . " -type f -regex '.*/[0-9a-f]\{10,20\}\.html'");
                foreach ($hexFiles as $file) {
                    if (empty($file)) continue;
                    $findings[] = [
                        'file_path' => trim($file),
                        'type' => 'Suspicious HTML (Hex-named)',
                        'details' => 'Likely SEO injection or backdoor.'
                    ];
                }

                // 2. Scan for common PHP shell patterns
                $shellPatterns = ['eval(base64_decode', 'shell_exec(', 'passthru(', 'system(', 'gzuncompress(base64_decode'];
                foreach ($shellPatterns as $pattern) {
                    // Exclude vendor, node_modules, storage, AND the Nimbus panel itself to avoid false positives
                    $cmd = "grep -rl " . escapeshellarg($pattern) . " " . escapeshellarg($path) . " --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=storage --exclude-dir=nimbus 2>/dev/null";
                    $files = $this->executeSudoCommand($cmd);
                    foreach ($files as $file) {
                        if (empty($file) || !is_string($file)) continue;
                        $filePath = trim($file);
                        
                        // Additional check to skip if it's inside the nimbus directory (backup safety)
                        if (str_contains($filePath, '/usr/local/nimbus')) continue;

                        $findings[] = [
                            'file_path' => $filePath,
                            'type' => 'Potential Web Shell',
                            'details' => "Contains suspicious function: $pattern"
                        ];
                    }
                }

                // 3. Scan with ClamAV if installed
                try {
                    $clamOutput = [];
                    $clamReturn = 0;
                    // --no-summary to reduce output, -r for recursive
                    exec("sudo clamscan -r --no-summary " . escapeshellarg($path) . " 2>/dev/null", $clamOutput, $clamReturn);
                    
                    // Clamscan returns 1 if viruses are found
                    if ($clamReturn === 1) {
                        foreach ($clamOutput as $line) {
                            if (str_contains($line, 'FOUND')) {
                                $parts = explode(': ', $line);
                                $filePath = trim($parts[0] ?? '');
                                $threatType = trim($parts[1] ?? 'Malware Detected');
                                
                                if ($filePath && !str_contains($filePath, '/usr/local/nimbus')) {
                                    $findings[] = [
                                        'file_path' => $filePath,
                                        'type' => 'ClamAV: ' . $threatType,
                                        'details' => 'Detected by ClamAV Antivirus engine.'
                                    ];
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning("Clamscan failed or not installed: " . $e->getMessage());
                }

                // 4. Scan with Maldet if installed
                try {
                    // Maldet is usually at /usr/local/sbin/maldet
                    // We can use -a for scan all in path
                    // But maldet scans are slow, we might want to skip for "Quick Scan"
                } catch (\Exception $e) {
                    \Log::warning("Maldet scan failed: " . $e->getMessage());
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

                return response()->json([
                    'success' => true,
                    'message' => 'Scan completed',
                    'findings_count' => count($findings)
                ]);
            } catch (\Exception $e) {
                \Log::error("Shield internal scan logic failed: " . $e->getMessage());
                return response()->json(['error' => $e->getMessage()], 500);
            } finally {
                try {
                    Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'idle']);
                } catch (\Exception $e) {
                    \Log::warning("Could not reset scan status: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            \Log::error("Shield startScan fatal error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
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
        $output = [];
        exec("sudo ufw status", $output);
        $statusLine = $output[0] ?? '';
        return str_contains($statusLine, 'active') && !str_contains($statusLine, 'inactive') ? 'Active' : 'Inactive';
    }

    /**
     * Get detailed firewall rules
     */
    public function getFirewallRules()
    {
        try {
            $output = [];
            exec("sudo ufw status numbered", $output);
            
            $rules = [];
            foreach ($output as $line) {
                if (preg_match('/^\[\s*(\d+)\]\s+(.*?)\s+(ALLOW|DENY)\s+(.*?)$/i', $line, $matches)) {
                    $rules[] = [
                        'index' => $matches[1],
                        'to' => trim($matches[2]),
                        'action' => $matches[3],
                        'from' => trim($matches[4])
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'status' => $this->getFirewallStatus(),
                'rules' => $rules
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add firewall rule
     */
    public function addFirewallRule(Request $request)
    {
        $port = $request->input('port');
        $action = $request->input('action', 'allow'); // allow or deny
        $proto = $request->input('proto', 'tcp');

        if (!$port) return response()->json(['error' => 'Port is required'], 400);

        try {
            $this->executeSudoCommand("ufw $action $port/$proto");
            return response()->json(['success' => true, 'message' => "Rule added: $action $port/$proto"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete firewall rule by index
     */
    public function deleteFirewallRule(Request $request)
    {
        $index = $request->input('index');
        if (!$index) return response()->json(['error' => 'Rule index is required'], 400);

        try {
            $this->executeSudoCommand("ufw --force delete $index");
            return response()->json(['success' => true, 'message' => "Rule removed"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle Firewall (On/Off)
     */
    public function toggleFirewall(Request $request)
    {
        $enable = $request->input('enable');
        $command = $enable ? "ufw --force enable" : "ufw disable";
        
        try {
            $this->executeSudoCommand($command);
            return response()->json(['success' => true, 'message' => "Firewall " . ($enable ? "enabled" : "disabled")]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Scan an uploaded file (Real-time protection)
     */
    public function scanUpload($filePath)
    {
        try {
            // Check with ClamAV instantly
            $output = [];
            $return = 0;
            exec("sudo clamscan --no-summary " . escapeshellarg($filePath), $output, $return);
            
            if ($return === 1) {
                return [
                    'safe' => false,
                    'reason' => 'Virus detected by ClamAV'
                ];
            }

            // Check for web shell patterns
            $threat = $this->scanSingleFile($filePath);
            if ($threat) {
                return [
                    'safe' => false,
                    'reason' => $threat['type'] . ': ' . $threat['details']
                ];
            }

            return ['safe' => true];
        } catch (\Exception $e) {
            \Log::error("Upload scan failed: " . $e->getMessage());
            return ['safe' => true]; // Allow on error to avoid blocking valid uploads if scanner breaks
        }
    }

    /**
     * Scan a single file for threats
     * Returns threat details or null if clean
     */
    public static function scanFile($filePath)
    {
        if (!file_exists($filePath)) return null;

        // 1. ClamAV Scan
        try {
            $output = [];
            $return = 0;
            // Use --no-summary for speed
            exec("sudo clamscan --no-summary " . escapeshellarg($filePath) . " 2>/dev/null", $output, $return);
            if ($return === 1) {
                foreach ($output as $line) {
                    if (str_contains($line, 'FOUND')) {
                        $parts = explode(': ', $line);
                        return [
                            'type' => 'ClamAV: ' . trim($parts[1] ?? 'Malware'),
                            'details' => 'Detected by ClamAV Antivirus engine during upload/scan.'
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning("ClamAV scanFile failed: " . $e->getMessage());
        }

        $filename = basename($filePath);
        
        // 2. Check for hex-named HTML files
        if (preg_match('/^[0-9a-f]{10,20}\.html$/', $filename)) {
            return [
                'type' => 'Suspicious HTML (Hex-named)',
                'details' => 'Likely SEO injection or backdoor.'
            ];
        }

        // 3. Check for shell patterns
        try {
            $content = file_get_contents($filePath);
            if ($content) {
                $shellPatterns = ['eval(base64_decode', 'shell_exec(', 'passthru(', 'system(', 'gzuncompress(base64_decode'];
                foreach ($shellPatterns as $pattern) {
                    if (str_contains($content, $pattern)) {
                        return [
                            'type' => 'Potential Web Shell',
                            'details' => "Contains suspicious function: $pattern"
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Shell pattern scanFile failed: " . $e->getMessage());
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
