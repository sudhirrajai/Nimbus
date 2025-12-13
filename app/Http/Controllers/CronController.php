<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CronController extends Controller
{
    /**
     * Display cron jobs management page
     */
    public function index()
    {
        return Inertia::render('Cron/Index');
    }

    /**
     * Get all cron jobs for www-data user
     */
    public function getJobs()
    {
        try {
            $jobs = [];
            
            // Get crontab for www-data user
            exec('sudo crontab -u www-data -l 2>/dev/null', $output, $code);
            
            if ($code === 0) {
                $id = 1;
                foreach ($output as $line) {
                    $line = trim($line);
                    
                    // Skip empty lines and comments
                    if (empty($line) || str_starts_with($line, '#')) continue;
                    
                    // Parse cron line: minute hour day month weekday command
                    if (preg_match('/^([\d\*\/\-,]+)\s+([\d\*\/\-,]+)\s+([\d\*\/\-,]+)\s+([\d\*\/\-,]+)\s+([\d\*\/\-,]+)\s+(.+)$/', $line, $matches)) {
                        $jobs[] = [
                            'id' => $id++,
                            'minute' => $matches[1],
                            'hour' => $matches[2],
                            'day' => $matches[3],
                            'month' => $matches[4],
                            'weekday' => $matches[5],
                            'command' => $matches[6],
                            'schedule' => "{$matches[1]} {$matches[2]} {$matches[3]} {$matches[4]} {$matches[5]}",
                            'raw' => $line
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'jobs' => $jobs
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new cron job
     */
    public function createJob(Request $request)
    {
        try {
            $request->validate([
                'minute' => 'required|string',
                'hour' => 'required|string',
                'day' => 'required|string',
                'month' => 'required|string',
                'weekday' => 'required|string',
                'command' => 'required|string'
            ]);

            $minute = $request->input('minute');
            $hour = $request->input('hour');
            $day = $request->input('day');
            $month = $request->input('month');
            $weekday = $request->input('weekday');
            $command = $request->input('command');

            $cronLine = "{$minute} {$hour} {$day} {$month} {$weekday} {$command}";

            // Get existing crontab
            exec('sudo crontab -u www-data -l 2>/dev/null', $existing, $code);
            $crontab = $code === 0 ? implode("\n", $existing) : '';
            
            // Add new job
            $crontab = trim($crontab) . "\n" . $cronLine . "\n";
            
            // Write back
            $tempFile = '/tmp/crontab_' . uniqid();
            file_put_contents($tempFile, $crontab);
            exec("sudo crontab -u www-data {$tempFile} 2>&1", $output, $resultCode);
            unlink($tempFile);

            if ($resultCode !== 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update crontab: ' . implode("\n", $output)
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cron job created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update existing cron job
     */
    public function updateJob(Request $request)
    {
        try {
            $request->validate([
                'old_command' => 'required|string',
                'minute' => 'required|string',
                'hour' => 'required|string',
                'day' => 'required|string',
                'month' => 'required|string',
                'weekday' => 'required|string',
                'command' => 'required|string'
            ]);

            $oldCommand = $request->input('old_command');
            $minute = $request->input('minute');
            $hour = $request->input('hour');
            $day = $request->input('day');
            $month = $request->input('month');
            $weekday = $request->input('weekday');
            $command = $request->input('command');

            $newCronLine = "{$minute} {$hour} {$day} {$month} {$weekday} {$command}";

            // Get existing crontab
            exec('sudo crontab -u www-data -l 2>/dev/null', $existing, $code);
            
            if ($code !== 0) {
                return response()->json(['error' => 'No crontab found'], 404);
            }

            // Replace the job
            $newLines = [];
            $found = false;
            foreach ($existing as $line) {
                if (str_contains($line, $oldCommand)) {
                    $newLines[] = $newCronLine;
                    $found = true;
                } else {
                    $newLines[] = $line;
                }
            }

            if (!$found) {
                return response()->json(['error' => 'Job not found'], 404);
            }

            // Write back
            $tempFile = '/tmp/crontab_' . uniqid();
            file_put_contents($tempFile, implode("\n", $newLines) . "\n");
            exec("sudo crontab -u www-data {$tempFile} 2>&1", $output, $resultCode);
            unlink($tempFile);

            return response()->json([
                'success' => true,
                'message' => 'Cron job updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete cron job
     */
    public function deleteJob(Request $request)
    {
        try {
            $command = $request->input('command');

            // Get existing crontab
            exec('sudo crontab -u www-data -l 2>/dev/null', $existing, $code);
            
            if ($code !== 0) {
                return response()->json(['error' => 'No crontab found'], 404);
            }

            // Remove the job
            $newLines = [];
            foreach ($existing as $line) {
                if (!str_contains($line, $command)) {
                    $newLines[] = $line;
                }
            }

            // Write back
            $tempFile = '/tmp/crontab_' . uniqid();
            file_put_contents($tempFile, implode("\n", $newLines) . "\n");
            exec("sudo crontab -u www-data {$tempFile} 2>&1", $output, $resultCode);
            unlink($tempFile);

            return response()->json([
                'success' => true,
                'message' => 'Cron job deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Run cron job immediately
     */
    public function runNow(Request $request)
    {
        try {
            $command = $request->input('command');
            
            // Run in background
            exec("cd /usr/local/nimbus && sudo -u www-data {$command} > /tmp/cron_output.log 2>&1 &");
            
            // Wait a moment and get output
            sleep(1);
            $output = file_exists('/tmp/cron_output.log') ? file_get_contents('/tmp/cron_output.log') : 'Job started in background';

            return response()->json([
                'success' => true,
                'message' => 'Job executed',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get schedule description
     */
    public function describeSchedule(Request $request)
    {
        $schedule = $request->input('schedule');
        
        // Parse and create human readable description
        $parts = explode(' ', $schedule);
        if (count($parts) !== 5) {
            return response()->json(['description' => 'Invalid schedule']);
        }

        list($minute, $hour, $day, $month, $weekday) = $parts;

        // Simple descriptions for common patterns
        if ($schedule === '* * * * *') {
            return response()->json(['description' => 'Every minute']);
        }
        if ($schedule === '0 * * * *') {
            return response()->json(['description' => 'Every hour']);
        }
        if ($schedule === '0 0 * * *') {
            return response()->json(['description' => 'Every day at midnight']);
        }
        if ($schedule === '0 0 * * 0') {
            return response()->json(['description' => 'Every Sunday at midnight']);
        }
        if ($schedule === '0 0 1 * *') {
            return response()->json(['description' => 'First day of every month at midnight']);
        }

        return response()->json([
            'description' => "At {$minute} minutes, hour {$hour}, day {$day}, month {$month}, weekday {$weekday}"
        ]);
    }
}
