<?php

namespace App\Http\Controllers;

use App\Models\WordPressSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WordPressController extends Controller
{
    private $basePath = '/var/www/';

    public function index()
    {
        return Inertia::render('WordPress/Index');
    }

    /**
     * Scan server for existing WordPress installations
     */
    public function scan()
    {
        try {
            $sites = [];
            $domains = glob($this->basePath . '*', GLOB_ONLYDIR);

            foreach ($domains as $domainPath) {
                $domain = basename($domainPath);
                if ($domain === 'html') continue;

                $checkPath = $domainPath;

                // Check for wp-config.php
                $wpConfig = $checkPath . '/wp-config.php';
                if (!file_exists($wpConfig)) continue;

                // Parse wp-config for DB info
                $config = $this->parseWpConfig($wpConfig);

                // Check WordPress version
                $versionFile = $checkPath . '/wp-includes/version.php';
                $wpVersion = 'Unknown';
                if (file_exists($versionFile)) {
                    $versionContent = file_get_contents($versionFile);
                    if (preg_match("/\\\$wp_version\s*=\s*'([^']+)'/", $versionContent, $m)) {
                        $wpVersion = $m[1];
                    }
                }

                // Get site title from DB if possible
                $siteTitle = $domain;
                $adminUser = null;
                $adminEmail = null;

                if ($config['db_name'] && $config['db_user']) {
                    try {
                        $prefix = $config['table_prefix'] ?? 'wp_';
                        $pdo = new \PDO(
                            "mysql:host=" . ($config['db_host'] ?? 'localhost') . ";dbname=" . $config['db_name'],
                            $config['db_user'],
                            $config['db_password'] ?? ''
                        );
                        
                        $stmt = $pdo->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'blogname' LIMIT 1");
                        if ($row = $stmt->fetch()) {
                            $siteTitle = $row['option_value'];
                        }

                        $stmt = $pdo->query("SELECT user_login, user_email FROM {$prefix}users WHERE ID = 1 LIMIT 1");
                        if ($row = $stmt->fetch()) {
                            $adminUser = $row['user_login'];
                            $adminEmail = $row['user_email'];
                        }
                    } catch (\Exception $e) {
                        Log::warning("WP scan: Could not connect to DB for {$domain}: " . $e->getMessage());
                    }
                }

                // Check SSL
                $sslEnabled = file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem");

                // Update or create record
                $site = WordPressSite::updateOrCreate(
                    ['domain' => $domain],
                    [
                        'path' => $checkPath,
                        'wp_version' => $wpVersion,
                        'db_name' => $config['db_name'] ?? null,
                        'db_user' => $config['db_user'] ?? null,
                        'db_password' => $config['db_password'] ?? null,
                        'admin_user' => $adminUser,
                        'admin_email' => $adminEmail,
                        'site_title' => $siteTitle,
                        'status' => 'active',
                        'ssl_enabled' => $sslEnabled,
                        'last_checked_at' => now(),
                    ]
                );
                $sites[] = $site;
            }

            $user = auth()->user();
            $accessibleDomains = $user->accessibleDomains();

            $query = WordPressSite::orderBy('domain');
            if (!$user->isRoot()) {
                $query->whereIn('domain', $accessibleDomains);
            }

            return response()->json([
                'success' => true,
                'sites' => $query->get(),
                'scanned' => count($sites),
            ]);
        } catch (\Exception $e) {
            Log::error("WordPress scan error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * List all tracked WordPress sites
     */
    public function list()
    {
        $user = auth()->user();
        $accessibleDomains = $user->accessibleDomains();

        $query = WordPressSite::orderBy('domain');
        if (!$user->isRoot()) {
            $query->whereIn('domain', $accessibleDomains);
        }

        return response()->json([
            'success' => true,
            'sites' => $query->get(),
        ]);
    }

    /**
     * Install WordPress on a domain
     */
    public function install(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'site_title' => 'required|string|max:255',
            'admin_user' => 'required|string|max:50',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email',
        ]);

        $domain = $request->input('domain');
        $domainPath = rtrim($this->basePath, '/') . '/' . $domain;

        if (!is_dir($domainPath)) {
            $dummy = '';
            $this->execCmd("sudo mkdir -p " . escapeshellarg($domainPath), $dummy);
            $this->execCmd("sudo chown www-data:www-data " . escapeshellarg($domainPath), $dummy);
        }

        // Check if WP already exists
        if (file_exists($domainPath . '/wp-config.php')) {
            return response()->json(['success' => false, 'error' => 'WordPress is already installed in this directory.'], 400);
        }

        // Auto-generate DB credentials
        $sanitizedDomain = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $dbName = 'wp_' . substr($sanitizedDomain, 0, 10) . '_' . Str::random(6);
        $dbUser = 'wp_' . substr($sanitizedDomain, 0, 10) . '_' . Str::random(6);
        $dbPass = Str::random(16);

        // Track in DB
        $site = WordPressSite::create([
            'domain' => $domain,
            'path' => $domainPath,
            'site_title' => $request->site_title,
            'admin_user' => $request->admin_user,
            'admin_email' => $request->admin_email,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPass,
            'status' => 'installing',
        ]);

        try {
            $output = '';

            // 0. Ensure wp-cli is installed
            $this->execCmd("if ! command -v wp &> /dev/null; then sudo curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && sudo chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp; fi", $output);

            // 1. Create database using DB facade
            \Illuminate\Support\Facades\DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
            \Illuminate\Support\Facades\DB::statement("CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}'");
            \Illuminate\Support\Facades\DB::statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost'");
            \Illuminate\Support\Facades\DB::statement("FLUSH PRIVILEGES");

            // 2. Download WordPress
            $this->execCmd("cd {$domainPath} && sudo -u www-data wp core download --force --allow-root 2>&1", $output);
            $this->execCmd("sudo rm -f {$domainPath}/index.html", $output);

            // 3. Create wp-config.php
            $dbNameArg = escapeshellarg($dbName);
            $dbUserArg = escapeshellarg($dbUser);
            $dbPassArg = escapeshellarg($dbPass);
            $this->execCmd("cd {$domainPath} && sudo -u www-data wp config create --dbname={$dbNameArg} --dbuser={$dbUserArg} --dbpass={$dbPassArg} --dbhost=localhost --allow-root 2>&1", $output);

            // 4. Install WordPress
            $url = 'http://' . $domain;
            $this->execCmd("cd {$domainPath} && sudo -u www-data wp core install --url={$url} --title=" . escapeshellarg($request->site_title) . " --admin_user=" . escapeshellarg($request->admin_user) . " --admin_password=" . escapeshellarg($request->admin_password) . " --admin_email=" . escapeshellarg($request->admin_email) . " --allow-root 2>&1", $output);

            // 5. Set permissions
            $this->execCmd("sudo chown -R www-data:www-data {$domainPath}", $output);
            $this->execCmd("sudo find {$domainPath} -type d -exec chmod 755 {} \\;", $output);
            $this->execCmd("sudo find {$domainPath} -type f -exec chmod 644 {} \\;", $output);

            // Check WP version
            $wpVersion = 'Unknown';
            $versionFile = $domainPath . '/wp-includes/version.php';
            if (file_exists($versionFile)) {
                $content = file_get_contents($versionFile);
                if (preg_match("/\\\$wp_version\s*=\s*'([^']+)'/", $content, $m)) {
                    $wpVersion = $m[1];
                }
            }

            $site->update([
                'status' => 'active',
                'wp_version' => $wpVersion,
                'last_checked_at' => now(),
            ]);

            Log::info("WordPress installed successfully on {$domain}");

            return response()->json([
                'success' => true,
                'message' => 'WordPress installed successfully!',
                'output' => $output,
                'site' => $site->fresh(),
            ]);
        } catch (\Exception $e) {
            $site->update(['status' => 'error']);
            Log::error("WordPress install error on {$domain}: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Change WordPress admin password
     */
    public function changePassword(Request $request, $id)
    {
        $request->validate([
            'username' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        $site = WordPressSite::findOrFail($id);
        $output = '';

        try {
            $cmd = "cd {$site->path} && sudo -u www-data wp user update " . escapeshellarg($request->username) . " --user_pass=" . escapeshellarg($request->new_password) . " --allow-root 2>&1";
            $this->execCmd($cmd, $output);

            return response()->json(['success' => true, 'message' => 'Password changed successfully.', 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get WordPress site details (plugins, themes, users)
     */
    public function details($id)
    {
        $site = WordPressSite::findOrFail($id);
        $details = ['plugins' => [], 'themes' => [], 'users' => [], 'core_update' => null];

        try {
            // Get plugins
            $pluginsJson = '';
            $this->execCmd("cd {$site->path} && sudo -u www-data wp plugin list --format=json --allow-root 2>&1", $pluginsJson);
            $details['plugins'] = json_decode($pluginsJson, true) ?? [];

            // Get themes
            $themesJson = '';
            $this->execCmd("cd {$site->path} && sudo -u www-data wp theme list --format=json --allow-root 2>&1", $themesJson);
            $details['themes'] = json_decode($themesJson, true) ?? [];

            // Get users
            $usersJson = '';
            $this->execCmd("cd {$site->path} && sudo -u www-data wp user list --format=json --fields=ID,user_login,user_email,roles --allow-root 2>&1", $usersJson);
            $details['users'] = json_decode($usersJson, true) ?? [];

            // Check for core update
            $coreCheck = '';
            $this->execCmd("cd {$site->path} && sudo -u www-data wp core check-update --format=json --allow-root 2>&1", $coreCheck);
            $coreUpdates = json_decode($coreCheck, true);
            if (is_array($coreUpdates) && count($coreUpdates) > 0) {
                $details['core_update'] = $coreUpdates[0];
            }
        } catch (\Exception $e) {
            Log::warning("WP details error for {$site->domain}: " . $e->getMessage());
        }

        return response()->json(['success' => true, 'site' => $site, 'details' => $details]);
    }

    /**
     * Update WordPress core
     */
    public function updateCore($id)
    {
        $site = WordPressSite::findOrFail($id);
        $output = '';

        try {
            $this->execCmd("cd {$site->path} && sudo -u www-data wp core update --allow-root 2>&1", $output);
            
            // Re-check version
            $versionFile = $site->path . '/wp-includes/version.php';
            if (file_exists($versionFile)) {
                $content = file_get_contents($versionFile);
                if (preg_match("/\\\$wp_version\s*=\s*'([^']+)'/", $content, $m)) {
                    $site->update(['wp_version' => $m[1], 'last_checked_at' => now()]);
                }
            }

            return response()->json(['success' => true, 'message' => 'WordPress core updated.', 'output' => $output, 'site' => $site->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update all plugins
     */
    public function updatePlugins($id)
    {
        $site = WordPressSite::findOrFail($id);
        $output = '';

        try {
            $this->execCmd("cd {$site->path} && sudo -u www-data wp plugin update --all --allow-root 2>&1", $output);
            return response()->json(['success' => true, 'message' => 'Plugins updated.', 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle plugin (activate/deactivate)
     */
    public function togglePlugin(Request $request, $id)
    {
        $request->validate(['plugin' => 'required|string', 'action' => 'required|in:activate,deactivate']);
        $site = WordPressSite::findOrFail($id);
        $output = '';

        try {
            $this->execCmd("cd {$site->path} && sudo -u www-data wp plugin {$request->action} " . escapeshellarg($request->plugin) . " --allow-root 2>&1", $output);
            return response()->json(['success' => true, 'message' => "Plugin {$request->action}d.", 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a WordPress site record (optionally with files/DB)
     */
    public function delete(Request $request, $id)
    {
        $site = WordPressSite::findOrFail($id);
        $output = '';

        try {
            if ($request->input('delete_files')) {
                // Wipe the whole directory minus logs, or let's be safer and just empty it except logs
                $this->execCmd("sudo find {$site->path} -mindepth 1 -maxdepth 1 ! -name 'logs' -exec rm -rf {} +", $output);
            }
            if ($request->input('delete_database') && $site->db_name) {
                \Illuminate\Support\Facades\DB::statement("DROP DATABASE IF EXISTS `{$site->db_name}`");
                if ($site->db_user) {
                    \Illuminate\Support\Facades\DB::statement("DROP USER IF EXISTS '{$site->db_user}'@'localhost'");
                }
            }

            $site->delete();

            return response()->json(['success' => true, 'message' => 'WordPress site removed.', 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse wp-config.php for database credentials
     */
    private function parseWpConfig($path)
    {
        $config = ['db_name' => null, 'db_user' => null, 'db_password' => null, 'db_host' => 'localhost', 'table_prefix' => 'wp_'];

        if (!file_exists($path)) return $config;

        $content = file_get_contents($path);

        if (preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $m)) {
            $config['db_name'] = $m[1];
        }
        if (preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $m)) {
            $config['db_user'] = $m[1];
        }
        if (preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]*?)['\"]\s*\)/", $content, $m)) {
            $config['db_password'] = $m[1];
        }
        if (preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $m)) {
            $config['db_host'] = $m[1];
        }
        if (preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
            $config['table_prefix'] = $m[1];
        }

        return $config;
    }

    /**
     * Generate a one-time auto-login URL for WordPress admin
     * Creates a temporary PHP script that authenticates and self-destructs
     */
    public function autoLogin($id)
    {
        $site = WordPressSite::findOrFail($id);

        if ($site->status !== 'active') {
            return response()->json(['success' => false, 'error' => 'Site is not active.'], 400);
        }

        $token = Str::random(64);
        $loginFile = $site->path . '/nimbus-login-' . substr($token, 0, 12) . '.php';
        $adminUser = $site->admin_user ?? 'admin';

        // Cleanup old tokens
        foreach (glob($site->path . '/nimbus-login-*.php') as $oldToken) {
            if (time() - filemtime($oldToken) > 60) {
                @unlink($oldToken);
            }
        }

        // Create self-destructing login script
        $script = <<<PHP
<?php
// Nimbus Auto-Login - One-time use, self-destructing
// Generated: {$token}

// Security: check token and expire after 60 seconds
\$created = filemtime(__FILE__);
if (time() - \$created > 60) {
    @unlink(__FILE__);
    die('Login link expired. Please generate a new one from the panel.');
}

// Load WordPress
define('ABSPATH', __DIR__ . '/');
require_once(ABSPATH . 'wp-load.php');

// Find the admin user
\$user = get_user_by('login', '{$adminUser}');
if (!\$user) {
    \$user = get_users(['role' => 'administrator', 'number' => 1]);
    \$user = !empty(\$user) ? \$user[0] : null;
}

if (!\$user) {
    wp_die('Admin user not found.');
}

// Set auth cookies and redirect
wp_clear_auth_cookie();
wp_set_current_user(\$user->ID);
wp_set_auth_cookie(\$user->ID, true);
do_action('wp_login', \$user->user_login, \$user);

wp_safe_redirect(admin_url());
exit;
PHP;

        try {
            file_put_contents($loginFile, $script);
            chmod($loginFile, 0644);
            // Ensure www-data owns it
            $dummy = '';
            $this->execCmd("sudo chown www-data:www-data " . escapeshellarg($loginFile), $dummy);

            $protocol = $site->ssl_enabled ? 'https' : 'http';
            
            $relativePath = basename($loginFile);
            $url = $protocol . '://' . $site->domain . '/' . $relativePath;

            Log::info("WP auto-login generated for {$site->domain} (user: {$adminUser})");

            return response()->json(['success' => true, 'url' => $url]);
        } catch (\Exception $e) {
            @unlink($loginFile);
            Log::error("WP auto-login error for {$site->domain}: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Execute a shell command and append output
     */
    private function execCmd($command, &$output)
    {
        $result = shell_exec($command);
        $output .= $result . "\n";
        return $result;
    }
}
