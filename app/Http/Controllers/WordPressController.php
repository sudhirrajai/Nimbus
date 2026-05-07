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

            // 1. Create database using sudo mysql (Laravel user might not have global privileges)
            $dbUserSafe = escapeshellarg($dbUser);
            $dbPassSafe = escapeshellarg($dbPass);
            $dbNameSafe = escapeshellarg($dbName); // For shell

            $this->execCmd("sudo mysql -e \"CREATE DATABASE IF NOT EXISTS \`{$dbName}\`\" 2>&1", $output);
            $this->execCmd("sudo mysql -e \"CREATE USER IF NOT EXISTS {$dbUserSafe}@'localhost' IDENTIFIED BY {$dbPassSafe}\" 2>&1", $output);
            $this->execCmd("sudo mysql -e \"GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO {$dbUserSafe}@'localhost'\" 2>&1", $output);
            $this->execCmd("sudo mysql -e \"FLUSH PRIVILEGES\" 2>&1", $output);

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
                // Must use sudo mysql instead of DB facade because nimbus DB user lacks DROP privileges
                $this->execCmd("sudo mysql -e \"DROP DATABASE IF EXISTS \`{$site->db_name}\`\" 2>&1", $output);
                if ($site->db_user) {
                    $userSafe = escapeshellarg($site->db_user);
                    $this->execCmd("sudo mysql -e \"DROP USER IF EXISTS {$userSafe}@'localhost'\" 2>&1", $output);
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

        $domainPath = escapeshellarg($site->path);
        $adminUser = escapeshellarg($site->admin_user ?? 'admin');
        $output = '';

        try {
            // 1. Ensure Nimbus SSO MU-Plugin exists
            $muPluginsDir = $site->path . '/wp-content/mu-plugins';
            $ssoPluginPath = $muPluginsDir . '/nimbus-sso.php';
            
            if (!file_exists($ssoPluginPath)) {
                $this->execCmd("sudo mkdir -p " . escapeshellarg($muPluginsDir), $output);
                
                $ssoPlugin = <<<'PHP'
<?php
/*
Plugin Name: Nimbus SSO
Description: Seamless secure login for Nimbus Control Panel
Version: 1.0
Author: Nimbus
*/
if (!defined('ABSPATH')) exit;

add_action('init', function() {
    if (isset($_GET['nimbus_sso_token']) && !empty($_GET['nimbus_sso_token'])) {
        $token = sanitize_text_field($_GET['nimbus_sso_token']);
        $stored_user_id = get_transient('nimbus_sso_' . $token);
        
        if ($stored_user_id) {
            delete_transient('nimbus_sso_' . $token); // Single use
            $user = get_userdata($stored_user_id);
            if ($user) {
                wp_clear_auth_cookie();
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);
                do_action('wp_login', $user->user_login, $user);
                wp_safe_redirect(admin_url());
                exit;
            }
        }
        
        wp_die('Invalid or expired login token. Please generate a new one from the Nimbus Panel.', 'Nimbus SSO Error', ['response' => 403]);
    }
});
PHP;
                $tempPlugin = '/tmp/nimbus-sso-' . uniqid() . '.php';
                file_put_contents($tempPlugin, $ssoPlugin);
                $this->execCmd("sudo mv " . escapeshellarg($tempPlugin) . " " . escapeshellarg($ssoPluginPath), $output);
                $this->execCmd("sudo chown www-data:www-data " . escapeshellarg($ssoPluginPath), $output);
                $this->execCmd("sudo chmod 644 " . escapeshellarg($ssoPluginPath), $output);
            }

            // 2. Get the Admin User ID via WP-CLI
            $userIdCmd = "cd {$domainPath} && sudo -u www-data wp user get {$adminUser} --field=ID 2>/dev/null";
            $userId = trim(shell_exec($userIdCmd));

            if (!$userId) {
                // Fallback to first administrator
                $userIdCmd = "cd {$domainPath} && sudo -u www-data wp user list --role=administrator --field=ID 2>/dev/null | head -n 1";
                $userId = trim(shell_exec($userIdCmd));
            }

            if (!$userId) {
                return response()->json(['success' => false, 'error' => 'Could not find a valid administrator user on this WordPress site.'], 404);
            }

            // 3. Generate Token and Save to Database via WP-CLI Transient (Expires in 60s)
            $token = Str::random(64);
            $tokenSafe = escapeshellarg('nimbus_sso_' . $token);
            $userIdSafe = escapeshellarg($userId);
            
            $this->execCmd("cd {$domainPath} && sudo -u www-data wp transient set {$tokenSafe} {$userIdSafe} 60", $output);

            // 4. Return the Magic Login URL
            $protocol = $site->ssl_enabled ? 'https' : 'http';
            $url = $protocol . '://' . $site->domain . '/wp-login.php?nimbus_sso_token=' . $token;

            Log::info("WP auto-login token generated for {$site->domain} (user ID: {$userId})");

            return response()->json(['success' => true, 'url' => $url]);

        } catch (\Exception $e) {
            Log::error("WP auto-login error for {$site->domain}: " . $e->getMessage() . "\nOutput: " . $output);
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
