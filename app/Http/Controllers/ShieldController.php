<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SecurityThreat;
use App\Models\Setting;
use App\Services\NotificationService;
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
            'alert_emails' => 'nullable|string',
            'ignored_policy' => 'nullable|string|in:flag_only,skip,quarantine',
            'excluded_paths' => 'nullable|string'
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
            Setting::updateOrCreate(
                ['key' => 'shield_ignored_policy'],
                ['value' => $request->input('ignored_policy', 'flag_only')]
            );
            if ($request->has('excluded_paths')) {
                Setting::updateOrCreate(
                    ['key' => 'shield_excluded_paths'],
                    ['value' => $request->input('excluded_paths', '')]
                );
            }

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
                ->get();

            $lastScan = SecurityThreat::max('detected_at');
            $stats = [
                'active_threats' => SecurityThreat::where('status', 'detected')->count(),
                'quarantined' => SecurityThreat::where('status', 'quarantined')->count(),
                'ignored' => SecurityThreat::where('status', 'ignored')->count(),
                'last_scan' => $lastScan ? \Illuminate\Support\Carbon::parse($lastScan)->diffForHumans() : 'Never',
                'firewall_status' => $this->getFirewallStatus(),
                'scan_status' => 'idle',
                'tools_installed' => $this->checkToolsInstalled(),
                'install_status' => Setting::where('key', 'shield_install_status')->value('value') ?: 'idle',
                'auto_scan_enabled' => Setting::where('key', 'shield_auto_scan')->value('value') === '1',
                'auto_scan_time' => Setting::where('key', 'shield_auto_scan_time')->value('value') ?: '03:00',
                'auto_quarantine' => Setting::where('key', 'shield_auto_quarantine')->value('value') === '1',
                'email_alerts' => Setting::where('key', 'shield_email_alerts')->value('value') === '1',
                'alert_emails' => Setting::where('key', 'shield_alert_emails')->value('value') ?: '',
                'ignored_policy' => Setting::where('key', 'shield_ignored_policy')->value('value') ?: 'flag_only',
                'excluded_paths' => Setting::where('key', 'shield_excluded_paths')->value('value') ?? "graphify-out\n.npm\n.cache\ncache\ncomposer.phar"
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
                Setting::updateOrCreate(['key' => 'shield_last_scan_at'], ['value' => now()->toDateTimeString()]);
            } catch (\Exception $e) {
                \Log::warning("Could not update scan status: " . $e->getMessage());
            }

            // Trigger background scan via Artisan command using full paths
            // Use nice and ionice to keep the system responsive
            $artisan = base_path('artisan');
            $phpBinary = (defined('PHP_BINARY') && PHP_BINARY && @is_executable(PHP_BINARY)) ? PHP_BINARY : '/usr/bin/php';
            $logFile = storage_path('logs/shield_scan.log');

            $cmd = "nohup nice -n 19 ionice -c 3 {$phpBinary} " . escapeshellarg($artisan) . " shield:scan " . escapeshellarg($path) . " >> " . escapeshellarg($logFile) . " 2>&1 &";
            exec($cmd);
            \Log::info("Nimbus Shield scan started for path: {$path}. Command: {$cmd}");

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
            Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'running']);
            Setting::updateOrCreate(['key' => 'shield_last_scan_at'], ['value' => now()->toDateTimeString()]);

            $autoQuarantine = Setting::where('key', 'shield_auto_quarantine')->value('value') === '1';
            $emailAlerts = Setting::where('key', 'shield_email_alerts')->value('value') !== '0';
            $alertEmails = Setting::where('key', 'shield_alert_emails')->value('value');

            // Resolve target emails
            $emails = [];
            if (!empty($alertEmails)) {
                $emails = array_map('trim', explode(',', $alertEmails));
            }
            if (empty($emails)) {
                $emails = NotificationService::resolveRecipientEmails();
            }

            if ($emailAlerts && !empty($emails)) {
                $htmlStart = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;'>";
                $htmlStart .= "<h2 style='color: #0f172a; margin-top: 0;'>🛡️ Nimbus Shield: Scan Initiated</h2>";
                $htmlStart .= "<p style='color: #475569;'>A security scan has been started on path:</p>";
                $htmlStart .= "<p style='background: #f1f5f9; padding: 10px 14px; border-radius: 6px; font-family: monospace; font-size: 14px; color: #0f172a;'><strong>" . htmlspecialchars($path) . "</strong></p>";
                $htmlStart .= "<p style='color: #475569;'>The scanner is actively checking for web shells, malicious PHP scripts, SEO spam files, and virus signatures via ClamAV.</p>";
                $htmlStart .= "<p style='color: #64748b; font-size: 13px;'>You will receive a detailed email report once the scan completes.</p>";
                $htmlStart .= "</div>";

                NotificationService::send("Nimbus Shield: Security Scan Started on " . htmlspecialchars($path), $htmlStart, $emails);
            }

            $findings = [];
            
            // 1. Scan for long hex-named HTML files (SEO injections)
            $hexFiles = [];
            exec("sudo find " . escapeshellarg($path) . " -type f -regex '.*/[0-9a-f]\{10,20\}\.html' 2>/dev/null", $hexFiles);
            foreach ($hexFiles as $file) {
                if (empty(trim($file))) continue;
                $findings[] = [
                    'file_path' => trim($file),
                    'type' => 'Suspicious HTML (Hex-named)',
                    'details' => 'Likely SEO injection or backdoor.'
                ];
            }

            $ignoredPolicy = Setting::where('key', 'shield_ignored_policy')->value('value') ?: 'flag_only';
            $customExcludedPaths = Setting::where('key', 'shield_excluded_paths')->value('value') ?: '';

            $excludeDirs = ['vendor', 'node_modules', 'storage', '.git', 'nimbus', 'graphify-out', '.npm', '.cache', 'cache', '.composer'];
            if (!empty($customExcludedPaths)) {
                foreach (preg_split('/[\r\n,]+/', $customExcludedPaths) as $cPath) {
                    $cPath = trim($cPath);
                    if (!empty($cPath)) {
                        $excludeDirs[] = basename($cPath);
                    }
                }
            }
            $excludeDirs = array_unique(array_filter($excludeDirs));
            $excludeArgs = '';
            foreach ($excludeDirs as $ed) {
                $excludeArgs .= ' --exclude-dir=' . escapeshellarg($ed);
            }
            $excludeArgs .= ' --exclude="composer.phar" --exclude="*.lock" --exclude="*.json" --exclude="*.md" --exclude="*.txt"';
            $includeArgs = ' --include="*.php" --include="*.phtml" --include="*.php5" --include="*.php7" --include="*.phps" --include="*.inc"';

            // 2. Scan for common PHP shell patterns (Only check executable PHP scripts, exclude JSON/AST/caches)
            $shellPatterns = ['eval(base64_decode', 'shell_exec(', 'passthru(', 'system(', 'gzuncompress(base64_decode'];
            foreach ($shellPatterns as $pattern) {
                $grepFiles = [];
                $grepReturn = 0;
                $cmd = "sudo grep -rl " . escapeshellarg($pattern) . " " . escapeshellarg($path) . " {$includeArgs} {$excludeArgs} 2>/dev/null";
                exec($cmd, $grepFiles, $grepReturn);
                if ($grepReturn === 0) {
                    foreach ($grepFiles as $file) {
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
            }

            // 3. Scan with ClamAV if installed
            try {
                $clamOutput = [];
                $clamReturn = 0;
                
                exec("which clamdscan 2>/dev/null", $whichClamdOutput, $whichClamdReturn);
                exec("which clamscan 2>/dev/null", $whichClamOutput, $whichClamReturn);

                if ($whichClamdReturn === 0) {
                    exec("sudo clamdscan --fdpass -m -r --no-summary " . escapeshellarg($path) . " 2>/dev/null", $clamOutput, $clamReturn);
                    if ($clamReturn === 2 && $whichClamReturn === 0) {
                        $clamOutput = [];
                        exec("sudo clamscan -r --no-summary --exclude-dir='vendor' --exclude-dir='node_modules' --exclude-dir='.git' --exclude-dir='graphify-out' --exclude-dir='.npm' " . escapeshellarg($path) . " 2>/dev/null", $clamOutput, $clamReturn);
                    }
                } elseif ($whichClamReturn === 0) {
                    exec("sudo clamscan -r --no-summary --exclude-dir='vendor' --exclude-dir='node_modules' --exclude-dir='.git' --exclude-dir='graphify-out' --exclude-dir='.npm' " . escapeshellarg($path) . " 2>/dev/null", $clamOutput, $clamReturn);
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

                // Check if this file has been marked as ignored/restored in the past
                $existingThreat = SecurityThreat::where('file_path', $filePath)->first();
                if ($existingThreat && $existingThreat->status === 'ignored') {
                    if ($ignoredPolicy === 'skip') {
                        // Completely skip this file from report and quarantine
                        continue;
                    }

                    if ($ignoredPolicy === 'flag_only') {
                        // Keep as ignored or flag, but NEVER move to quarantine
                        $cleanDetails = explode(' | Quarantined to:', $details)[0];
                        $existingThreat->update([
                            'type' => $finding['type'],
                            'details' => $cleanDetails . ' (Quarantine skipped: File restored/ignored by administrator)',
                            'detected_at' => now()
                        ]);
                        continue;
                    }
                }

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

            if ($emailAlerts && !empty($emails)) {
                $threatCount = count($findings);
                $statusBadgeColor = $threatCount > 0 ? '#ef4444' : '#10b981';
                $statusBadgeText = $threatCount > 0 ? "{$threatCount} Threats Detected" : "Clean (0 Threats Detected)";

                $htmlReport = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;'>";
                $htmlReport .= "<div style='border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px;'>";
                $htmlReport .= "<h2 style='color: #0f172a; margin: 0;'>🛡️ Nimbus Shield: Scan Report</h2>";
                $htmlReport .= "</div>";

                $htmlReport .= "<div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px;'>";
                $htmlReport .= "<p style='margin: 4px 0; color: #475569;'><strong>Scanned Path:</strong> <span style='font-family: monospace; color: #0f172a;'>" . htmlspecialchars($path) . "</span></p>";
                $htmlReport .= "<p style='margin: 4px 0; color: #475569;'><strong>Scan Completed:</strong> " . now()->format('Y-m-d H:i:s T') . "</p>";
                $htmlReport .= "<p style='margin: 4px 0; color: #475569;'><strong>Status:</strong> <span style='display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: 600; color: #ffffff; background-color: {$statusBadgeColor};'>" . $statusBadgeText . "</span></p>";
                $htmlReport .= "<p style='margin: 4px 0; color: #475569;'><strong>Auto-Quarantine:</strong> " . ($autoQuarantine ? "<span style='color: #10b981; font-weight: 600;'>Enabled</span>" : "<span style='color: #64748b;'>Disabled</span>") . "</p>";
                $htmlReport .= "</div>";

                if ($threatCount > 0) {
                    $htmlReport .= "<h4 style='color: #dc2626; margin-top: 20px; margin-bottom: 10px;'>⚠️ Detected Threats:</h4>";
                    $htmlReport .= "<table style='width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; border: 1px solid #cbd5e1;'>";
                    $htmlReport .= "<thead style='background: #f1f5f9;'><tr><th style='padding: 8px 10px; text-align: left; border: 1px solid #cbd5e1;'>File</th><th style='padding: 8px 10px; text-align: left; border: 1px solid #cbd5e1;'>Threat</th><th style='padding: 8px 10px; text-align: left; border: 1px solid #cbd5e1;'>Status</th></tr></thead><tbody>";
                    foreach ($findings as $f) {
                        $fStatus = $autoQuarantine ? "<span style='color: #d97706; font-weight: 600;'>Quarantined</span>" : "<span style='color: #ef4444; font-weight: 600;'>Detected</span>";
                        $htmlReport .= "<tr><td style='padding: 8px 10px; border: 1px solid #cbd5e1; word-break: break-all; font-family: monospace;'>" . htmlspecialchars($f['file_path']) . "</td><td style='padding: 8px 10px; border: 1px solid #cbd5e1;'>" . htmlspecialchars($f['type']) . "</td><td style='padding: 8px 10px; border: 1px solid #cbd5e1;'>{$fStatus}</td></tr>";
                    }
                    $htmlReport .= "</tbody></table>";
                } else {
                    $htmlReport .= "<div style='background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 14px 16px; color: #065f46;'>";
                    $htmlReport .= "<strong>✅ All clear!</strong> No malware, web shells, or compromised files were detected during this scan.";
                    $htmlReport .= "</div>";
                }

                $htmlReport .= "<p style='color: #94a3b8; font-size: 12px; margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 12px;'>This is an automated security alert from Nimbus Panel Shield.</p>";
                $htmlReport .= "</div>";

                $subject = "Nimbus Shield: Scan Completed (" . ($threatCount > 0 ? "{$threatCount} Threats Detected" : "Clean") . ") - " . basename($path);
                NotificationService::send($subject, $htmlReport, $emails);
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

    public function stopScan()
    {
        exec("sudo pkill -f 'shield:scan' 2>/dev/null");
        exec("sudo pkill -f 'clamdscan' 2>/dev/null");
        exec("sudo pkill -f 'clamscan' 2>/dev/null");
        Setting::updateOrCreate(['key' => 'shield_scan_status'], ['value' => 'idle']);
        return response()->json(['success' => true, 'message' => 'Scan stopped and status reset']);
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

                $dir = dirname($originalPath);
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($dir));
                $this->executeSudoCommand("mv " . escapeshellarg($quarantinedPath) . " " . escapeshellarg($originalPath));
                $this->executeSudoCommand("chown www-data:www-data " . escapeshellarg($originalPath));
                $this->executeSudoCommand("chmod 644 " . escapeshellarg($originalPath));

                // Find all threats pointing to the same quarantined file and restore them in DB
                $relatedThreats = SecurityThreat::where('status', 'quarantined')
                    ->where('details', 'like', "%Quarantined to: {$quarantinedPath}%")
                    ->get();

                foreach ($relatedThreats as $rThreat) {
                    $cleanDetails = explode(' | Quarantined to:', $rThreat->details)[0];
                    $rThreat->update([
                        'status' => 'ignored',
                        'details' => $cleanDetails . ' (Restored & Ignored)',
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

    /**
     * Safely preview a threat file content (live or quarantined)
     */
    public function previewFile(Request $request)
    {
        $id = $request->input('id');
        $threat = SecurityThreat::find($id);
        
        if (!$threat) return response()->json(['error' => 'Threat not found'], 404);

        $isQuarantined = ($threat->status === 'quarantined');
        $quarantinedPath = null;
        if (preg_match('/Quarantined to: (.+)$/', $threat->details, $matches)) {
            $quarantinedPath = trim($matches[1]);
        }

        $readPath = ($isQuarantined && $quarantinedPath) ? $quarantinedPath : $threat->file_path;

        $output = [];
        $returnCode = 0;
        exec("sudo test -f " . escapeshellarg($readPath), $output, $returnCode);
        if ($returnCode !== 0) {
            return response()->json(['error' => 'File does not exist on disk (' . basename($readPath) . ')'], 404);
        }

        $escaped = escapeshellarg($readPath);
        $sizeOutput = [];
        exec("sudo stat -c %s {$escaped} 2>/dev/null || sudo wc -c < {$escaped} 2>/dev/null", $sizeOutput);
        $fileSize = (int) trim($sizeOutput[0] ?? '0');

        $content = '';
        if ($fileSize > 2 * 1024 * 1024) {
            $out = [];
            exec("sudo head -n 2500 {$escaped} 2>/dev/null", $out);
            $content = implode("\n", $out) . "\n\n... [File truncated: showing first 2,500 lines]";
        } else {
            $out = [];
            exec("sudo cat {$escaped} 2>/dev/null", $out);
            $content = implode("\n", $out);
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        $lineCount = substr_count($content, "\n") + 1;

        // Extract domain and relative path for file editor navigation
        $domain = '';
        $relPath = '';
        if (preg_match('#^/var/www/([^/]+)(?:/(.*))?$#', $threat->file_path, $m)) {
            $domain = $m[1];
            $relPath = $m[2] ?? '';
        }

        return response()->json([
            'success' => true,
            'threat' => $threat,
            'file_path' => $threat->file_path,
            'quarantined_path' => $quarantinedPath,
            'is_quarantined' => $isQuarantined,
            'size' => $fileSize,
            'lines' => $lineCount,
            'domain' => $domain,
            'relative_path' => $relPath,
            'content' => $content
        ]);
    }

    /**
     * Mark a threat as ignored / whitelisted
     */
    public function ignoreThreat(Request $request)
    {
        $id = $request->input('id');
        $threat = SecurityThreat::find($id);
        if (!$threat) return response()->json(['error' => 'Threat not found'], 404);

        // If it was quarantined, restore it first
        if ($threat->status === 'quarantined' && preg_match('/Quarantined to: (.+)$/', $threat->details, $matches)) {
            $quarantinedPath = trim($matches[1]);
            $originalPath = $threat->file_path;

            $output = [];
            $returnCode = 0;
            exec("sudo test -f " . escapeshellarg($quarantinedPath), $output, $returnCode);
            if ($returnCode === 0) {
                $dir = dirname($originalPath);
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($dir));
                $this->executeSudoCommand("mv " . escapeshellarg($quarantinedPath) . " " . escapeshellarg($originalPath));
                $this->executeSudoCommand("chown www-data:www-data " . escapeshellarg($originalPath));
                $this->executeSudoCommand("chmod 644 " . escapeshellarg($originalPath));
            }
        }

        $cleanDetails = explode(' | Quarantined to:', $threat->details)[0];
        $threat->update([
            'status' => 'ignored',
            'details' => $cleanDetails . ' (Ignored by administrator)',
            'resolved_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Threat marked as Ignored. It will be skipped/protected from future quarantine.'
        ]);
    }

    /**
     * Reset an ignored threat back to detected
     */
    public function unignoreThreat(Request $request)
    {
        $id = $request->input('id');
        $threat = SecurityThreat::find($id);
        if (!$threat) return response()->json(['error' => 'Threat not found'], 404);

        $cleanDetails = str_replace(' (Ignored by administrator)', '', $threat->details);
        $cleanDetails = str_replace(' (Restored & Ignored)', '', $cleanDetails);
        $threat->update([
            'status' => 'detected',
            'details' => $cleanDetails,
            'resolved_at' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Threat reset to Detected status'
        ]);
    }

    /**
     * Bulk restore all or selected quarantined files
     */
    public function bulkRestore(Request $request)
    {
        $ids = $request->input('ids');
        $query = SecurityThreat::query();
        if (!empty($ids) && is_array($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->where('status', 'quarantined');
        }
        $threats = $query->get();

        $restoredCount = 0;
        $errors = [];

        foreach ($threats as $threat) {
            if (preg_match('/Quarantined to: (.+)$/', $threat->details, $matches)) {
                $quarantinedPath = trim($matches[1]);
                $originalPath = $threat->file_path;

                $output = [];
                $returnCode = 0;
                exec("sudo test -f " . escapeshellarg($quarantinedPath), $output, $returnCode);
                if ($returnCode === 0) {
                    try {
                        $dir = dirname($originalPath);
                        $this->executeSudoCommand("mkdir -p " . escapeshellarg($dir));
                        $this->executeSudoCommand("mv " . escapeshellarg($quarantinedPath) . " " . escapeshellarg($originalPath));
                        $this->executeSudoCommand("chown www-data:www-data " . escapeshellarg($originalPath));
                        $this->executeSudoCommand("chmod 644 " . escapeshellarg($originalPath));

                        // Find all threats pointing to this same quarantined file
                        $relatedThreats = SecurityThreat::where('details', 'like', "%Quarantined to: {$quarantinedPath}%")->get();
                        foreach ($relatedThreats as $rThreat) {
                            $cleanDetails = explode(' | Quarantined to:', $rThreat->details)[0];
                            $rThreat->update([
                                'status' => 'ignored',
                                'details' => $cleanDetails . ' (Restored & Ignored)',
                                'resolved_at' => now()
                            ]);
                        }
                        $restoredCount++;
                    } catch (\Exception $e) {
                        $errors[] = basename($originalPath) . ': ' . $e->getMessage();
                    }
                } else {
                    $cleanDetails = explode(' | Quarantined to:', $threat->details)[0];
                    $threat->update([
                        'status' => 'ignored',
                        'details' => $cleanDetails . ' (File already restored or removed)',
                        'resolved_at' => now()
                    ]);
                    $restoredCount++;
                }
            } else {
                // If threat is not quarantined (e.g. detected), mark as safe / ignored
                $cleanDetails = explode(' | Quarantined to:', $threat->details)[0];
                $threat->update([
                    'status' => 'ignored',
                    'details' => $cleanDetails . ' (Restored & Whitelisted)',
                    'resolved_at' => now()
                ]);
                $restoredCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully recovered {$restoredCount} file(s).",
            'restored_count' => $restoredCount,
            'errors' => $errors
        ]);
    }

    private function sendEncryptedEmail($to, $subject, $htmlContent)
    {
        return NotificationService::send($subject, $htmlContent, [$to]);
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
        $enable = (bool) $request->input('enable');
        
        try {
            if ($enable) {
                // Safeguard: Ensure critical management & web ports are allowed before enabling UFW
                // to prevent administrator lockout.
                $this->executeSudoCommand("ufw allow 22/tcp");
                $this->executeSudoCommand("ufw allow 80/tcp");
                $this->executeSudoCommand("ufw allow 443/tcp");
                $panelPort = (int) env('PORT', 8090);
                if ($panelPort > 0 && !in_array($panelPort, [22, 80, 443])) {
                    $this->executeSudoCommand("ufw allow {$panelPort}/tcp");
                }
                $this->executeSudoCommand("ufw --force enable");
            } else {
                $this->executeSudoCommand("ufw disable");
            }

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
                exec("sudo clamdscan --fdpass --no-summary " . escapeshellarg($filePath) . " 2>/dev/null", $output, $return);
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

        // 3. Check for shell patterns (only in executable PHP scripts)
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'phtml', 'php5', 'php7', 'inc'])) {
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
