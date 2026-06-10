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

    public function updateSettings(Request $request)
    {
        $request->validate([
            'auto_scan_enabled' => 'required|boolean',
            'auto_scan_time' => 'required|string|regex:/^[0-2][0-9]:[0-5][0-9]$/',
            'auto_quarantine' => 'required|boolean',
            'email_alerts' => 'required|boolean',
            'alert_emails' => 'nullable|string'
        ]);

        try {
            Setting::updateOrCreate(
                ['key' => 'shield_auto_scan'],
                ['value' => $request->auto_scan_enabled ? '1' : '0']
            );
            Setting::updateOrCreate(
                ['key' => 'shield_auto_scan_time'],
                ['value' => $request->auto_scan_time]
            );
            Setting::updateOrCreate(
                ['key' => 'shield_auto_quarantine'],
                ['value' => $request->auto_quarantine ? '1' : '0']
            );
            Setting::updateOrCreate(
                ['key' => 'shield_email_alerts'],
                ['value' => $request->email_alerts ? '1' : '0']
            );
            Setting::updateOrCreate(
                ['key' => 'shield_alert_emails'],
                ['value' => $request->alert_emails ?: '']
            );

            return response()->json([
                'success' => true,
                'message' => 'Security settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
                'scan_status' => 'idle',
                'tools_installed' => $this->checkToolsInstalled(),
                'install_status' => Setting::where('key', 'shield_install_status')->value('value') ?: 'idle',
                'auto_scan_enabled' => Setting::where('key', 'shield_auto_scan')->value('value') === '1',
                'auto_scan_time' => Setting::where('key', 'shield_auto_scan_time')->value('value') ?: '03:00',
                'auto_quarantine' => Setting::where('key', 'shield_auto_quarantine')->value('value') === '1',
                'email_alerts' => Setting::where('key', 'shield_email_alerts')->value('value') === '1',
                'alert_emails' => Setting::where('key', 'shield_alert_emails')->value('value') ?: ''
            ];

            try {
                $stats['scan_status'] = Setting::where('key', 'shield_scan_status')->value('value') ?: 'idle';
                
                // Check if installation finished
                if ($stats['install_status'] === 'installing' && file_exists('/tmp/nimbus_shield_install_done')) {
                    Setting::updateOrCreate(['key' => 'shield_install_status'], ['value' => 'idle']);
                    unlink('/tmp/nimbus_shield_install_done');
                    $stats['install_status'] = 'idle';
                    $stats['tools_installed'] = $this->checkToolsInstalled();
                }
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
     * Perform a security scan (Trigger)
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

            // Trigger background scan via Artisan command
            // Use nice and ionice to keep the system responsive
            $cmd = "nice -n 19 ionice -c 3 php artisan shield:scan " . escapeshellarg($path);
            exec("nohup $cmd > /dev/null 2>&1 &");

            return response()->json([
                'success' => true,
                'message' => 'Scan started in background. You can monitor progress on this page.'
            ]);
        } catch (\Exception $e) {
            \Log::error("Shield startScan fatal error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Internal scanning logic, called from Artisan command
     */
    public function runInternalScan($path)
    {
        // Allow the script to continue after disconnect
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        try {
            $autoQuarantine = Setting::where('key', 'shield_auto_quarantine')->value('value') === '1';
            $emailAlerts = Setting::where('key', 'shield_email_alerts')->value('value') === '1';
            $alertEmails = Setting::where('key', 'shield_alert_emails')->value('value');

            if ($emailAlerts && !empty($alertEmails)) {
                $emails = array_map('trim', explode(',', $alertEmails));
                foreach ($emails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->sendEncryptedEmail(
                            $email,
                            "Nimbus Shield: Scan Started",
                            "<p>A security scan has been initiated on path: <strong>$path</strong></p><p>You will receive another email once the scan completes with a detailed report.</p>"
                        );
                    }
                }
            }

            $findings = [];
            
            // 1. Scan for long hex-named HTML files (SEO injections)
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
                $cmd = "grep -rl " . escapeshellarg($pattern) . " " . escapeshellarg($path) . " --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=storage --exclude-dir=nimbus 2>/dev/null";
                $files = $this->executeSudoCommand($cmd);
                foreach ($files as $file) {
                    if (empty($file) || !is_string($file)) continue;
                    $filePath = trim($file);
                    
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
                
                exec("which clamdscan 2>/dev/null", $whichOutput, $whichReturn);
                if ($whichReturn === 0) {
                    exec("sudo clamdscan -r --no-summary " . escapeshellarg($path) . " 2>/dev/null", $clamOutput, $clamReturn);
                    if ($clamReturn === 2) {
                        exec("sudo clamscan -r --no-summary " . escapeshellarg($path) . " 2>/dev/null", $clamOutput, $clamReturn);
                    }
                } else {
                    exec("sudo clamscan -r --no-summary " . escapeshellarg($path) . " 2>/dev/null", $clamOutput, $clamReturn);
                }
                
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

            // Save findings to database and process auto-quarantine
            $quarantineDir = storage_path('app/quarantine');
            if ($autoQuarantine && !is_dir($quarantineDir)) {
                try {
                    $this->executeSudoCommand("mkdir -p " . escapeshellarg($quarantineDir));
                    $this->executeSudoCommand("chown www-data:www-data " . escapeshellarg($quarantineDir));
                    $this->executeSudoCommand("chmod 770 " . escapeshellarg($quarantineDir));
                } catch (\Exception $e) {
                    \Log::warning("Could not create quarantine directory during scan: " . $e->getMessage());
                }
            }

            $quarantinedFiles = []; // Map of original_path => quarantined_path

            foreach ($findings as $finding) {
                $status = 'detected';
                $details = $finding['details'];
                $filePath = $finding['file_path'];

                if ($autoQuarantine) {
                    if (isset($quarantinedFiles[$filePath])) {
                        // File was already quarantined in this scan!
                        $status = 'quarantined';
                        $details .= " | Quarantined to: " . $quarantinedFiles[$filePath];
                    } else {
                        $filename = basename($filePath) . '.' . time() . '_' . rand(1000, 9999) . '.bak';
                        $destination = $quarantineDir . '/' . $filename;
                        
                        try {
                            $this->executeSudoCommand("mv " . escapeshellarg($filePath) . " " . escapeshellarg($destination));
                            $this->executeSudoCommand("chmod 000 " . escapeshellarg($destination));
                            $status = 'quarantined';
                            $details .= " | Quarantined to: $destination";
                            $quarantinedFiles[$filePath] = $destination;
                        } catch (\Exception $e) {
                            \Log::warning("Auto-quarantine failed for {$filePath}: " . $e->getMessage());
                        }
                    }
                }

                SecurityThreat::updateOrCreate(
                    ['file_path' => $finding['file_path']],
                    [
                        'type' => $finding['type'],
                        'details' => $details,
                        'status' => $status,
                        'detected_at' => now(),
                        'resolved_at' => $status === 'quarantined' ? now() : null
                    ]
                );
            }

            \Log::info("Shield scan completed for $path. Findings: " . count($findings));

            if ($emailAlerts && !empty($alertEmails)) {
                $emails = array_map('trim', explode(',', $alertEmails));
                $htmlReport = "<h3>Nimbus Shield: Scan Completed</h3>";
                $htmlReport .= "<p>Scan path: <strong>$path</strong></p>";
                $htmlReport .= "<p>Total threats found: <strong>" . count($findings) . "</strong></p>";
                
                if (count($findings) > 0) {
                    $htmlReport .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
                    $htmlReport .= "<thead><tr><th>File Path</th><th>Type</th><th>Status</th></tr></thead><tbody>";
                    foreach ($findings as $finding) {
                        $fileStatus = $autoQuarantine ? "Quarantined" : "Detected";
                        $htmlReport .= "<tr><td>{$finding['file_path']}</td><td>{$finding['type']}</td><td>{$fileStatus}</td></tr>";
                    }
                    $htmlReport .= "</tbody></table>";
                } else {
                    $htmlReport .= "<p>No threats were detected. Your system is clean.</p>";
                }

                foreach ($emails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->sendEncryptedEmail($email, "Nimbus Shield: Scan Completed - " . count($findings) . " Threats Found", $htmlReport);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Shield internal scan logic failed: " . $e->getMessage());
        } finally {
            try {
                Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'idle']);
            } catch (\Exception $e) {
                \Log::warning("Could not reset scan status: " . $e->getMessage());
            }
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
            try {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($quarantineDir));
                $this->executeSudoCommand("chown www-data:www-data " . escapeshellarg($quarantineDir));
                $this->executeSudoCommand("chmod 770 " . escapeshellarg($quarantineDir));
            } catch (\Exception $e) {
                \Log::warning("Could not create quarantine directory during manual quarantine: " . $e->getMessage());
            }
        }

        // Check if there is another threat on the same file that is already quarantined
        $existingQuarantine = SecurityThreat::where('file_path', $source)
            ->where('status', 'quarantined')
            ->where('details', 'like', '%Quarantined to:%')
            ->first();

        $destination = null;
        if ($existingQuarantine) {
            if (preg_match('/Quarantined to: (.+)$/', $existingQuarantine->details, $matches)) {
                $destination = trim($matches[1]);
            }
        }

        try {
            if ($destination) {
                // Already quarantined, just link this threat to the same file
                $threat->update([
                    'status' => 'quarantined',
                    'details' => $threat->details . " | Quarantined to: $destination",
                    'resolved_at' => now()
                ]);
            } else {
                // Not quarantined yet, perform move
                $filename = basename($source) . '.' . time() . '_' . rand(1000, 9999) . '.bak';
                $destination = $quarantineDir . '/' . $filename;
                
                $this->executeSudoCommand("mv " . escapeshellarg($source) . " " . escapeshellarg($destination));
                $this->executeSudoCommand("chmod 000 " . escapeshellarg($destination)); // Make it unreadable

                $threat->update([
                    'status' => 'quarantined',
                    'details' => $threat->details . " | Quarantined to: $destination",
                    'resolved_at' => now()
                ]);
            }

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
            $quarantinedPath = null;
            if (preg_match('/Quarantined to: (.+)$/', $threat->details, $matches)) {
                $quarantinedPath = trim($matches[1]);
            }

            if ($quarantinedPath) {
                // If it is quarantined, delete the quarantined file using sudo
                $output = [];
                $returnCode = 0;
                exec("sudo test -f " . escapeshellarg($quarantinedPath), $output, $returnCode);
                if ($returnCode === 0) {
                    $this->executeSudoCommand("rm -f " . escapeshellarg($quarantinedPath));
                }

                // Update all threats pointing to the same quarantined file
                $relatedThreats = SecurityThreat::where('details', 'like', "%Quarantined to: {$quarantinedPath}%")
                    ->get();

                foreach ($relatedThreats as $rThreat) {
                    $rThreat->update([
                        'status' => 'deleted',
                        'resolved_at' => now()
                    ]);
                }
            } else {
                // If not quarantined, delete the original file if it exists
                $output = [];
                $returnCode = 0;
                exec("sudo test -e " . escapeshellarg($threat->file_path), $output, $returnCode);
                if ($returnCode === 0) {
                    $this->executeSudoCommand("rm -rf " . escapeshellarg($threat->file_path));
                }

                $threat->update([
                    'status' => 'deleted',
                    'resolved_at' => now()
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Threat deleted permanently']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore quarantined file
     */
    public function restoreQuarantine(Request $request)
    {
        $id = $request->input('id');
        $threat = SecurityThreat::find($id);
        
        if (!$threat) return response()->json(['error' => 'Threat not found'], 404);
        if ($threat->status !== 'quarantined') return response()->json(['error' => 'Threat is not quarantined'], 400);

        if (preg_match('/Quarantined to: (.+)$/', $threat->details, $matches)) {
            $quarantinedPath = trim($matches[1]);
            $originalPath = $threat->file_path;

            try {
                // Check if file exists using sudo to bypass permissions on /tmp or quarantine directory
                $output = [];
                $returnCode = 0;
                exec("sudo test -f " . escapeshellarg($quarantinedPath), $output, $returnCode);
                if ($returnCode !== 0) {
                     return response()->json(['error' => 'Quarantined file missing'], 404);
                }

                $this->executeSudoCommand("mv " . escapeshellarg($quarantinedPath) . " " . escapeshellarg($originalPath));
                $this->executeSudoCommand("chmod 644 " . escapeshellarg($originalPath));

                // Find all threats pointing to the same quarantined file and restore them in DB
                $relatedThreats = SecurityThreat::where('status', 'quarantined')
                    ->where('details', 'like', "%Quarantined to: {$quarantinedPath}%")
                    ->get();

                foreach ($relatedThreats as $rThreat) {
                    $cleanDetails = explode(' | Quarantined to:', $rThreat->details)[0];
                    $rThreat->update([
                        'status' => 'ignored',
                        'details' => $cleanDetails,
                        'resolved_at' => now()
                    ]);
                }

                return response()->json(['success' => true, 'message' => 'File restored successfully']);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        return response()->json(['error' => 'Could not determine quarantine path'], 400);
    }

    private function sendEncryptedEmail($to, $subject, $htmlContent)
    {
        $apiUrl = 'https://vmcore.in/api/send-encrypted-email';
        $apiKey = 'vmk_ZZALOAMF78GByDGlGe3buSlly2Z32s9r7ey8KJf3w7VojizG';
        $encKey = 'UOFE3D52L3fjfCvew0rd2ed/GgwCzN521vlgJ7hmlm0=';

        $rawKey = base64_decode($encKey);
        
        $encryptValue = function($value) use ($rawKey) {
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($value, 'AES-256-CBC', $rawKey, 0, $iv);
            $mac = hash_hmac('sha256', base64_encode($iv) . $encrypted, $rawKey);

            return base64_encode(json_encode([
                'iv'    => base64_encode($iv),
                'value' => $encrypted,
                'mac'   => $mac,
                'tag'   => '',
            ]));
        };

        $payload = [
            'to_email'          => $to,
            'encrypted_subject' => $encryptValue($subject),
            'encrypted_content' => $encryptValue($htmlContent),
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "X-Api-Key: $apiKey",
                "Accept: application/json",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 10
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
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

        // Validation
        if (!$port || !preg_match('/^[a-zA-Z0-9:]+$/', $port)) {
            return response()->json(['error' => 'Invalid port format'], 400);
        }
        
        $action = in_array($action, ['allow', 'deny']) ? $action : 'allow';
        $proto = in_array($proto, ['tcp', 'udp', 'any']) ? $proto : 'tcp';

        try {
            $cmd = "ufw " . $action . " " . escapeshellarg($port) . "/" . $proto;
            $this->executeSudoCommand($cmd);
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
        if (!$index || !is_numeric($index)) {
            return response()->json(['error' => 'Invalid rule index'], 400);
        }

        try {
            $this->executeSudoCommand("ufw --force delete " . escapeshellarg($index));
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
            // Try clamdscan (daemon - ultra-fast) first, fallback to clamscan if daemon is not running or missing
            exec("which clamdscan 2>/dev/null", $whichOutput, $whichReturn);
            if ($whichReturn === 0) {
                exec("sudo clamdscan --no-summary " . escapeshellarg($filePath) . " 2>/dev/null", $output, $return);
                if ($return === 2) {
                    exec("sudo clamscan --no-summary " . escapeshellarg($filePath) . " 2>/dev/null", $output, $return);
                }
            } else {
                exec("sudo clamscan --no-summary " . escapeshellarg($filePath) . " 2>/dev/null", $output, $return);
            }
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

    /**
     * Check if security tools are installed
     */
    private function checkToolsInstalled()
    {
        $clamav = shell_exec('which clamscan');
        $ufw = shell_exec('which ufw');
        $maldet = shell_exec('which maldet');
        
        return [
            'clamav' => !empty($clamav),
            'ufw' => !empty($ufw),
            'maldet' => !empty($maldet),
            'all' => (!empty($clamav) && !empty($ufw) && !empty($maldet))
        ];
    }

    /**
     * Start background installation of security tools
     */
    public function installTools()
    {
        try {
            $status = Setting::where('key', 'shield_install_status')->value('value');
            if ($status === 'installing') {
                return response()->json(['error' => 'Installation already in progress'], 409);
            }

            Setting::updateOrCreate(['key' => 'shield_install_status'], ['value' => 'installing']);

            // Build the install script
            $installCmd = "sudo apt-get update && sudo apt-get install -y clamav clamav-daemon ufw && " .
                         "wget http://www.rfxn.com/downloads/maldetect-current.tar.gz && " .
                         "tar -xzf maldetect-current.tar.gz && " .
                         "cd maldetect-* && sudo ./install.sh && " .
                         "cd .. && rm -rf maldetect-* && " .
                         "echo 'done' > /tmp/nimbus_shield_install_done";

            // Run in background
            exec("nohup sh -c \"$installCmd\" > /dev/null 2>&1 &");

            // Start a watcher to reset status when done
            // We'll check the /tmp file in getStatus
            
            return response()->json(['success' => true, 'message' => 'Installation started in background']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Fail2Ban status, active jails, and list of banned IPs
     */
    public function getFail2BanStatus()
    {
        try {
            $installed = !empty(shell_exec('which fail2ban-client'));
            $active = false;
            $jails = [];
            $bannedIps = [];
            
            // Check background installation status
            $installStatus = Setting::where('key', 'fail2ban_install_status')->value('value') ?: 'idle';
            if ($installStatus === 'installing' && file_exists('/tmp/nimbus_fail2ban_install_done')) {
                Setting::updateOrCreate(['key' => 'fail2ban_install_status'], ['value' => 'idle']);
                if (file_exists('/tmp/nimbus_fail2ban_install_done')) {
                    unlink('/tmp/nimbus_fail2ban_install_done');
                }
                $installStatus = 'idle';
                $installed = true;
            }

            if ($installed) {
                $statusActiveOutput = exec("systemctl is-active fail2ban");
                $active = ($statusActiveOutput === 'active');

                if ($active) {
                    // Get list of jails
                    $statusOutput = $this->executeSudoCommand("fail2ban-client status");
                    $jailList = [];
                    foreach ($statusOutput as $line) {
                        $line = trim($line);
                        if (preg_match('/Jail list:\s*(.*)/i', $line, $matches)) {
                            $jailListStr = trim($matches[1]);
                            if (!empty($jailListStr)) {
                                // Split by space and/or comma
                                $jailList = preg_split('/[\s,]+/', $jailListStr);
                                $jailList = array_filter($jailList);
                            }
                        }
                    }

                    // Parse each jail
                    foreach ($jailList as $jailName) {
                        $jailName = trim($jailName);
                        if (empty($jailName)) continue;
                        
                        $jailInfo = $this->parseJailStatus($jailName);
                        $jails[] = $jailInfo;

                        foreach ($jailInfo['banned_ips'] as $ip) {
                            $bannedIps[] = [
                                'ip' => $ip,
                                'jail' => $jailName
                            ];
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'installed' => $installed,
                'active' => $active,
                'jails' => $jails,
                'banned_ips' => $bannedIps,
                'install_status' => $installStatus
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse the status of a single Fail2Ban jail
     */
    private function parseJailStatus($jailName)
    {
        $output = $this->executeSudoCommand("fail2ban-client status " . escapeshellarg($jailName));
        $currentlyFailed = 0;
        $totalFailed = 0;
        $currentlyBanned = 0;
        $bannedIps = [];

        foreach ($output as $line) {
            $line = trim($line);
            if (preg_match('/Currently failed:\s*(\d+)/i', $line, $matches)) {
                $currentlyFailed = (int)$matches[1];
            } elseif (preg_match('/Total failed:\s*(\d+)/i', $line, $matches)) {
                $totalFailed = (int)$matches[1];
            } elseif (preg_match('/Currently banned:\s*(\d+)/i', $line, $matches)) {
                $currentlyBanned = (int)$matches[1];
            } elseif (preg_match('/Banned IP list:\s*(.*)/i', $line, $matches)) {
                $ipListStr = trim($matches[1]);
                if (!empty($ipListStr)) {
                    $bannedIps = preg_split('/[\s,]+/', $ipListStr);
                    $bannedIps = array_filter($bannedIps);
                }
            }
        }

        return [
            'name' => $jailName,
            'currently_failed' => $currentlyFailed,
            'total_failed' => $totalFailed,
            'currently_banned' => $currentlyBanned,
            'banned_ips' => array_values($bannedIps)
        ];
    }

    /**
     * Trigger Fail2Ban background installation
     */
    public function installFail2Ban()
    {
        try {
            $status = Setting::where('key', 'fail2ban_install_status')->value('value');
            if ($status === 'installing') {
                return response()->json(['error' => 'Installation already in progress'], 409);
            }

            Setting::updateOrCreate(['key' => 'fail2ban_install_status'], ['value' => 'installing']);

            $installCmd = "sudo apt-get update && sudo apt-get install -y fail2ban && echo 'done' > /tmp/nimbus_fail2ban_install_done";
            exec("nohup sh -c \"$installCmd\" > /dev/null 2>&1 &");

            return response()->json(['success' => true, 'message' => 'Fail2Ban installation started in background']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle Fail2Ban service (Start/Stop)
     */
    public function toggleFail2Ban(Request $request)
    {
        $enable = $request->input('enable');
        $command = $enable ? "systemctl start fail2ban" : "systemctl stop fail2ban";
        
        try {
            $this->executeSudoCommand($command);
            return response()->json(['success' => true, 'message' => "Fail2Ban " . ($enable ? "started" : "stopped")]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unban an IP address from a jail
     */
    public function unbanIp(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'jail' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/'
        ]);

        try {
            $cmd = "fail2ban-client set " . escapeshellarg($request->jail) . " unbanip " . escapeshellarg($request->ip);
            $this->executeSudoCommand($cmd);
            return response()->json(['success' => true, 'message' => "IP {$request->ip} unbanned from jail {$request->jail}"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ban an IP address manually in a jail
     */
    public function banIp(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'jail' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/'
        ]);

        try {
            $cmd = "fail2ban-client set " . escapeshellarg($request->jail) . " banip " . escapeshellarg($request->ip);
            $this->executeSudoCommand($cmd);
            return response()->json(['success' => true, 'message' => "IP {$request->ip} banned in jail {$request->jail}"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function executeSudoCommand($command)
    {
        $output = [];
        $returnCode = 0;
        \Log::debug("Executing sudo command in Shield: sudo $command");
        exec("sudo $command 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = "Command execution failed: " . implode("\n", $output);
            \Log::error($errorMsg);
            throw new \Exception($errorMsg);
        }

        return $output;
    }
}
