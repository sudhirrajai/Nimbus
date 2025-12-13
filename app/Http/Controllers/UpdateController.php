<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UpdateController extends Controller
{
    // Current version - increment this when releasing new versions
    const CURRENT_VERSION = '1.0.0';
    
    // GitHub repository
    const GITHUB_REPO = 'sudhirrajai/Nimbus';
    
    /**
     * Display updates page
     */
    public function index()
    {
        return Inertia::render('Updates/Index', [
            'currentVersion' => self::CURRENT_VERSION
        ]);
    }

    /**
     * Check for updates from GitHub
     */
    public function checkForUpdates()
    {
        try {
            // Cache the check for 5 minutes to avoid rate limiting
            $updateInfo = Cache::remember('nimbus_update_check', 300, function () {
                return $this->fetchUpdateInfo();
            });
            
            return response()->json([
                'success' => true,
                'currentVersion' => self::CURRENT_VERSION,
                'latestVersion' => $updateInfo['version'] ?? self::CURRENT_VERSION,
                'hasUpdate' => version_compare($updateInfo['version'] ?? self::CURRENT_VERSION, self::CURRENT_VERSION, '>'),
                'changelog' => $updateInfo['changelog'] ?? [],
                'releaseDate' => $updateInfo['releaseDate'] ?? null,
                'releaseUrl' => $updateInfo['releaseUrl'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'currentVersion' => self::CURRENT_VERSION
            ]);
        }
    }

    /**
     * Fetch update info from GitHub
     */
    private function fetchUpdateInfo()
    {
        // Try to get latest release from GitHub API
        $response = Http::timeout(10)->get('https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest');
        
        if ($response->successful()) {
            $release = $response->json();
            
            // Parse version from tag (remove 'v' prefix if present)
            $version = ltrim($release['tag_name'] ?? '1.0.0', 'v');
            
            // Parse changelog from body
            $changelog = $this->parseChangelog($release['body'] ?? '');
            
            return [
                'version' => $version,
                'changelog' => $changelog,
                'releaseDate' => $release['published_at'] ?? null,
                'releaseUrl' => $release['html_url'] ?? null
            ];
        }
        
        // Fallback: Try to read from VERSION file in repo
        $versionResponse = Http::timeout(10)->get('https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/main/VERSION');
        
        if ($versionResponse->successful()) {
            return [
                'version' => trim($versionResponse->body()),
                'changelog' => [],
                'releaseDate' => null,
                'releaseUrl' => 'https://github.com/' . self::GITHUB_REPO . '/releases'
            ];
        }
        
        return [
            'version' => self::CURRENT_VERSION,
            'changelog' => [],
            'releaseDate' => null,
            'releaseUrl' => null
        ];
    }

    /**
     * Parse changelog from release body
     */
    private function parseChangelog($body)
    {
        if (empty($body)) return [];
        
        $changelog = [];
        $lines = explode("\n", $body);
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Match lines starting with - or * or •
            if (preg_match('/^[-*•]\s*(.+)$/', $line, $matches)) {
                $changelog[] = $matches[1];
            }
        }
        
        return $changelog;
    }

    /**
     * Perform update
     */
    public function performUpdate()
    {
        try {
            $logFile = storage_path('logs/update.log');
            $statusFile = storage_path('logs/update_status.txt');
            
            // Clear old logs
            file_put_contents($logFile, "Update started at " . date('Y-m-d H:i:s') . "\n");
            file_put_contents($statusFile, 'running');
            
            // Update script - uses sudo and detects PHP version
            $script = <<<'BASH'
#!/bin/bash
set -e
cd /usr/local/nimbus

# Detect PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "Detected PHP version: $PHP_VERSION"

echo ""
echo "Backing up current version..."
sudo cp -r /usr/local/nimbus /tmp/nimbus_backup_$(date +%Y%m%d_%H%M%S) 2>/dev/null || true

echo ""
echo "Ensuring required PHP extensions are installed..."
sudo apt-get update -qq
sudo apt-get install -y -qq php${PHP_VERSION}-xml php${PHP_VERSION}-dom php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring php${PHP_VERSION}-curl 2>&1 || true

echo ""
echo "Stashing local changes..."
sudo git stash 2>&1 || true

echo ""
echo "Pulling latest changes from main branch..."
sudo git fetch origin main 2>&1
sudo git reset --hard origin/main 2>&1

echo ""
echo "Installing composer dependencies..."
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1

echo ""
echo "Running database migrations..."
sudo php artisan migrate --force 2>&1

echo ""
echo "Clearing caches..."
sudo php artisan config:clear 2>&1
sudo php artisan cache:clear 2>&1 || true
sudo php artisan view:clear 2>&1
sudo php artisan route:clear 2>&1

echo ""
echo "Building frontend assets..."
sudo npm install 2>&1
sudo npm run build 2>&1

echo ""
echo "Setting permissions..."
sudo chown -R www-data:www-data /usr/local/nimbus
sudo chmod -R 775 /usr/local/nimbus/storage
sudo chmod -R 775 /usr/local/nimbus/bootstrap/cache
sudo touch /usr/local/nimbus/storage/logs/laravel.log
sudo chown www-data:www-data /usr/local/nimbus/storage/logs/laravel.log

echo ""
echo "Restarting PHP-FPM..."
sudo systemctl restart php${PHP_VERSION}-fpm 2>&1 || true

echo ""
echo "Update completed successfully!"
BASH;

            $tempScript = "/tmp/nimbus_update.sh";
            file_put_contents($tempScript, $script);
            chmod($tempScript, 0755);
            
            // Run update in background
            exec("sudo bash {$tempScript} >> {$logFile} 2>&1 &");
            
            return response()->json([
                'success' => true,
                'message' => 'Update started. Please wait...'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get update progress/status
     */
    public function getUpdateStatus()
    {
        $logFile = storage_path('logs/update.log');
        $statusFile = storage_path('logs/update_status.txt');
        
        $log = file_exists($logFile) ? file_get_contents($logFile) : '';
        $status = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'idle';
        
        // Check if update is complete
        if (strpos($log, 'Update completed successfully') !== false) {
            file_put_contents($statusFile, 'done');
            $status = 'done';
        }
        
        return response()->json([
            'status' => $status,
            'log' => $log
        ]);
    }

    /**
     * Force refresh update check
     */
    public function forceCheck()
    {
        Cache::forget('nimbus_update_check');
        return $this->checkForUpdates();
    }
}
