<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DatabaseController extends Controller
{
    private $phpMyAdminPath = '/usr/share/phpmyadmin';
    private $credentialsPath = '/usr/local/nimbus/storage/app/phpmyadmin_credentials.json';

    /**
     * Display database management page
     */
    public function index()
    {
        return Inertia::render('Database/Index');
    }

    /**
     * Get phpMyAdmin installation status
     */
    public function getStatus()
    {
        try {
            $isInstalled = file_exists($this->phpMyAdminPath);
            $hasCredentials = file_exists($this->credentialsPath);
            
            return response()->json([
                'phpMyAdminInstalled' => $isInstalled,
                'credentialsSet' => $hasCredentials,
                'firstTimeSetup' => $isInstalled && !$hasCredentials
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get database status: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Install phpMyAdmin
     */
    public function installPhpMyAdmin()
    {
        try {
            // Check if already installed
            if (file_exists($this->phpMyAdminPath)) {
                return response()->json(['error' => 'phpMyAdmin is already installed'], 400);
            }

            // Check if another installation is in progress
            $lockFile = storage_path('logs/nimbus_install.lock');
            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json([
                    'error' => "Another installation is in progress: {$lockContent}. Please wait for it to complete."
                ], 409);
            }
            
            // Create lock file
            file_put_contents($lockFile, 'phpMyAdmin installation');

            $logFile = storage_path('logs/phpmyadmin_install.log');
            $statusFile = storage_path('logs/phpmyadmin_status.txt');
            
            // Clear old logs
            file_put_contents($logFile, "phpMyAdmin installation started at " . date('Y-m-d H:i:s') . "\n");
            file_put_contents($statusFile, 'running');
            
            // Generate credentials
            $adminUser = 'nimbus_admin';
            $adminPass = Str::random(16);
            
            // Detect PHP version
            $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            
            // Install script
            $script = <<<BASH
#!/bin/bash
cd /usr/local/nimbus

echo "Setting up debconf selections..."
echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | sudo debconf-set-selections
echo 'phpmyadmin phpmyadmin/app-password-confirm password ' | sudo debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/admin-pass password ' | sudo debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/app-pass password ' | sudo debconf-set-selections
echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect none' | sudo debconf-set-selections

echo ""
echo "Updating package cache..."
sudo apt-get update 2>&1

echo ""
echo "Installing phpMyAdmin..."
sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y phpmyadmin 2>&1

if [ ! -d "/usr/share/phpmyadmin" ]; then
    echo "ERROR: phpMyAdmin installation failed!"
    exit 1
fi

echo ""
echo "Creating MySQL admin user..."
sudo mysql -e "DROP USER IF EXISTS '{$adminUser}'@'localhost'" 2>&1 || true
sudo mysql -e "CREATE USER '{$adminUser}'@'localhost' IDENTIFIED BY '{$adminPass}'" 2>&1
sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO '{$adminUser}'@'localhost' WITH GRANT OPTION" 2>&1
sudo mysql -e "FLUSH PRIVILEGES" 2>&1

echo ""
echo "Configuring nginx..."
sudo mkdir -p /etc/nginx/snippets

sudo tee /etc/nginx/snippets/phpmyadmin.conf > /dev/null << 'NGINX'
location /phpmyadmin {
    alias /usr/share/phpmyadmin;
    index index.php;

    location ~ ^/phpmyadmin/(.+\.php)$ {
        alias /usr/share/phpmyadmin/\$1;
        fastcgi_pass unix:/var/run/php/php{$phpVersion}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$request_filename;
    }

    location ~* ^/phpmyadmin/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ {
        alias /usr/share/phpmyadmin/\$1;
    }
}
NGINX

# Add include to nimbus config if not present
if ! grep -q 'phpmyadmin.conf' /etc/nginx/sites-available/nimbus; then
    sudo sed -i '/^}/i\    include snippets/phpmyadmin.conf;' /etc/nginx/sites-available/nimbus
fi

echo ""
echo "Testing nginx configuration..."
sudo nginx -t 2>&1

echo ""
echo "Reloading nginx..."
sudo systemctl reload nginx 2>&1

# Remove lock file
rm -f /usr/local/nimbus/storage/logs/nimbus_install.lock

echo ""
echo "Installation completed successfully!"
echo "Username: {$adminUser}"
echo "Password: {$adminPass}"
BASH;

            $tempScript = "/tmp/phpmyadmin_install.sh";
            file_put_contents($tempScript, $script);
            chmod($tempScript, 0755);
            
            // Run install in background
            exec("sudo bash {$tempScript} >> {$logFile} 2>&1 &");
            
            // Save credentials to file
            $credentials = [
                'username' => $adminUser,
                'password' => $adminPass,
                'created_at' => now()->toDateTimeString(),
                'url' => '/phpmyadmin'
            ];
            
            $credentialsDir = dirname($this->credentialsPath);
            if (!File::exists($credentialsDir)) {
                File::makeDirectory($credentialsDir, 0755, true);
            }
            File::put($this->credentialsPath, json_encode($credentials, JSON_PRETTY_PRINT));

            return response()->json([
                'message' => 'phpMyAdmin installation started...',
                'credentials' => $credentials,
                'polling' => true
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to install phpMyAdmin: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get phpMyAdmin install status/log
     */
    public function getInstallStatus()
    {
        $logFile = storage_path('logs/phpmyadmin_install.log');
        $statusFile = storage_path('logs/phpmyadmin_status.txt');
        
        $log = file_exists($logFile) ? file_get_contents($logFile) : '';
        $status = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'idle';
        
        // Check if install is complete
        if (strpos($log, 'Installation completed successfully') !== false) {
            file_put_contents($statusFile, 'done');
            $status = 'done';
        } elseif (strpos($log, 'ERROR:') !== false) {
            file_put_contents($statusFile, 'error');
            $status = 'error';
        }
        
        return response()->json([
            'status' => $status,
            'log' => $log,
            'installed' => file_exists($this->phpMyAdminPath)
        ]);
    }

    /**
     * Download credentials file
     */
    public function downloadCredentials()
    {
        try {
            if (!file_exists($this->credentialsPath)) {
                return response()->json(['error' => 'Credentials not found'], 404);
            }

            $credentials = json_decode(File::get($this->credentialsPath), true);
            
            $content = "phpMyAdmin Credentials\n";
            $content .= "======================\n\n";
            $content .= "URL: /phpmyadmin\n";
            $content .= "Username: {$credentials['username']}\n";
            $content .= "Password: {$credentials['password']}\n";
            $content .= "Created: {$credentials['created_at']}\n\n";
            $content .= "KEEP THIS FILE SAFE!\n";

            return response($content)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'attachment; filename="phpmyadmin_credentials.txt"');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reinstall phpMyAdmin (remove and install again)
     */
    public function reinstallPhpMyAdmin()
    {
        try {
            $output = [];
            
            // Remove existing phpMyAdmin
            exec("sudo apt-get purge -y phpmyadmin 2>&1", $output, $code);
            exec("sudo apt-get autoremove -y 2>&1", $output, $code);
            
            // Remove credentials file
            if (file_exists($this->credentialsPath)) {
                unlink($this->credentialsPath);
            }
            
            // Remove nginx config
            $snippetPath = '/etc/nginx/snippets/phpmyadmin.conf';
            if (file_exists($snippetPath)) {
                $this->executeSudoCommand("rm -f {$snippetPath}");
            }
            
            // Set debconf selections to avoid interactive prompts
            exec("echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | sudo debconf-set-selections 2>&1", $output, $code);
            exec("echo 'phpmyadmin phpmyadmin/app-password-confirm password ' | sudo debconf-set-selections 2>&1", $output, $code);
            exec("echo 'phpmyadmin phpmyadmin/mysql/admin-pass password ' | sudo debconf-set-selections 2>&1", $output, $code);
            exec("echo 'phpmyadmin phpmyadmin/mysql/app-pass password ' | sudo debconf-set-selections 2>&1", $output, $code);
            exec("echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect none' | sudo debconf-set-selections 2>&1", $output, $code);
            
            // Update and reinstall
            exec("sudo apt-get update 2>&1", $output, $code);
            exec("sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y phpmyadmin 2>&1", $output, $code);
            
            if ($code !== 0) {
                return response()->json([
                    'error' => 'Failed to reinstall phpMyAdmin',
                    'details' => implode("\n", $output)
                ], 500);
            }
            
            // Generate new admin credentials
            $adminUser = 'nimbus_admin';
            $adminPass = Str::random(16);
            
            // Drop old user if exists and create new
            exec("sudo mysql -e \"DROP USER IF EXISTS '{$adminUser}'@'localhost'\" 2>&1", $output, $code);
            $this->createMySQLUser($adminUser, $adminPass, true);
            
            // Configure nginx for phpMyAdmin
            $this->configurePhpMyAdminNginx();
            
            // Save credentials
            $credentials = [
                'username' => $adminUser,
                'password' => $adminPass,
                'created_at' => now()->toDateTimeString(),
                'url' => '/phpmyadmin'
            ];
            
            $credentialsDir = dirname($this->credentialsPath);
            if (!File::exists($credentialsDir)) {
                File::makeDirectory($credentialsDir, 0755, true);
            }
            File::put($this->credentialsPath, json_encode($credentials, JSON_PRETTY_PRINT));
            
            // Reload nginx
            $this->executeSudoCommand("systemctl reload nginx");
            
            return response()->json([
                'message' => 'phpMyAdmin reinstalled successfully',
                'credentials' => $credentials,
                'showCredentials' => true
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to reinstall phpMyAdmin: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all databases with their users
     */
    public function getDatabases()
    {
        try {
            // Get all databases using sudo mysql (to see all, not just nimbus user's)
            $output = [];
            exec("sudo mysql -N -e \"SHOW DATABASES\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to query databases: " . implode("\n", $output));
            }
            
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin', 'nimbus', 'roundcube'];
            
            $result = [];
            
            foreach ($output as $dbName) {
                $dbName = trim($dbName);
                if (empty($dbName) || in_array($dbName, $systemDbs)) {
                    continue;
                }

                // Get users with access to this database
                $users = $this->getDatabaseUsers($dbName);
                
                $result[] = [
                    'name' => $dbName,
                    'users' => $users,
                    'size' => $this->getDatabaseSize($dbName)
                ];
            }

            return response()->json(['databases' => $result]);
        } catch (\Exception $e) {
            \Log::error("Failed to get databases: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get users with access to a database
     */
    private function getDatabaseUsers($dbName)
    {
        try {
            $users = DB::select("
                SELECT DISTINCT User, Host 
                FROM mysql.db 
                WHERE Db = ? OR Db = ?
            ", [$dbName, str_replace('_', '\\_', $dbName)]);

            $result = [];
            foreach ($users as $user) {
                if ($user->User === 'root' || $user->User === '') continue;
                
                $privileges = $this->getUserPrivileges($user->User, $user->Host, $dbName);
                $result[] = [
                    'username' => $user->User,
                    'host' => $user->Host,
                    'privileges' => $privileges
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user privileges for a database
     */
    private function getUserPrivileges($username, $host, $dbName)
    {
        try {
            $grants = DB::select("SHOW GRANTS FOR ?@?", [$username, $host]);
            $privileges = [];
            
            foreach ($grants as $grant) {
                $grantStr = reset((array)$grant);
                if (stripos($grantStr, $dbName) !== false || stripos($grantStr, '*.*') !== false) {
                    // Parse privileges from GRANT statement
                    if (preg_match('/GRANT (.+) ON/', $grantStr, $matches)) {
                        $privList = explode(',', $matches[1]);
                        foreach ($privList as $priv) {
                            $privileges[] = trim($priv);
                        }
                    }
                }
            }

            return array_unique($privileges);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get database size
     */
    private function getDatabaseSize($dbName)
    {
        try {
            $result = DB::selectOne("
                SELECT SUM(data_length + index_length) as size 
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$dbName]);
            
            return $this->formatBytes($result->size ?? 0);
        } catch (\Exception $e) {
            return '0 B';
        }
    }

    /**
     * Create a new database
     */
    public function createDatabase(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:64|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'
            ]);

            $dbName = $request->input('name');
            
            // Check if database exists using sudo mysql
            $output = [];
            exec("sudo mysql -N -e \"SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'\" 2>&1", $output, $code);
            if (!empty($output) && trim($output[0]) !== '') {
                return response()->json(['error' => 'Database already exists'], 400);
            }

            // Create database using sudo mysql
            $output = [];
            exec("sudo mysql -e \"CREATE DATABASE \`{$dbName}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to create database: " . implode("\n", $output));
            }

            // Grant access to nimbus_admin (phpMyAdmin user) so database shows in phpMyAdmin
            exec("sudo mysql -e \"GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO 'nimbus_admin'@'localhost'; FLUSH PRIVILEGES;\" 2>&1");

            return response()->json([
                'message' => "Database '{$dbName}' created successfully"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to create database: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a database
     */
    public function deleteDatabase(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:64'
            ]);

            $dbName = $request->input('name');
            
            // Prevent deleting system databases
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin', 'nimbus'];
            if (in_array($dbName, $systemDbs)) {
                return response()->json(['error' => 'Cannot delete system database'], 403);
            }

            // Delete database using sudo mysql
            $output = [];
            exec("sudo mysql -e \"DROP DATABASE IF EXISTS \`{$dbName}\`\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to delete database: " . implode("\n", $output));
            }

            return response()->json([
                'message' => "Database '{$dbName}' deleted successfully"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to delete database: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new MySQL user
     */
    public function createUser(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:32|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                'password' => 'required|string|min:8',
                'host' => 'nullable|string'
            ]);

            $username = $request->input('username');
            $password = $request->input('password');
            $host = $request->input('host', 'localhost');

            // Check if user exists using sudo mysql
            $output = [];
            exec("sudo mysql -N -e \"SELECT User FROM mysql.user WHERE User = '{$username}' AND Host = '{$host}'\" 2>&1", $output, $code);
            if (!empty($output) && $code === 0 && trim($output[0]) !== '') {
                return response()->json(['error' => 'User already exists'], 400);
            }

            // Create user using sudo mysql
            exec("sudo mysql -e \"CREATE USER '{$username}'@'{$host}' IDENTIFIED BY '{$password}'\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to create user: " . implode("\n", $output));
            }

            return response()->json([
                'message' => "User '{$username}'@'{$host}' created successfully",
                'username' => $username,
                'host' => $host
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to create user: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a MySQL user
     */
    public function deleteUser(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:32',
                'host' => 'nullable|string'
            ]);

            $username = $request->input('username');
            $host = $request->input('host', 'localhost');

            // Prevent deleting root
            if ($username === 'root') {
                return response()->json(['error' => 'Cannot delete root user'], 403);
            }

            // Delete user using sudo mysql
            $output = [];
            exec("sudo mysql -e \"DROP USER IF EXISTS '{$username}'@'{$host}'\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to delete user: " . implode("\n", $output));
            }

            return response()->json([
                'message' => "User '{$username}'@'{$host}' deleted successfully"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to delete user: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Assign user to database with permissions
     */
    public function assignUser(Request $request)
    {
        try {
            $request->validate([
                'database' => 'required|string|max:64',
                'username' => 'required|string|max:32',
                'host' => 'nullable|string',
                'privileges' => 'required|array'
            ]);

            $database = $request->input('database');
            $username = $request->input('username');
            $host = $request->input('host', 'localhost');
            $privileges = $request->input('privileges');

            // Validate privileges
            $allowedPrivileges = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'ALTER', 'INDEX', 'CREATE TEMPORARY TABLES', 'LOCK TABLES', 'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE', 'ALTER ROUTINE', 'EVENT', 'TRIGGER'];
            $privileges = array_intersect($privileges, $allowedPrivileges);
            
            if (empty($privileges)) {
                return response()->json(['error' => 'At least one valid privilege is required'], 400);
            }

            $privilegeStr = implode(', ', $privileges);
            
            // Grant privileges using sudo mysql
            $output = [];
            exec("sudo mysql -e \"GRANT {$privilegeStr} ON \`{$database}\`.* TO '{$username}'@'{$host}'\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to grant privileges: " . implode("\n", $output));
            }
            
            exec("sudo mysql -e \"FLUSH PRIVILEGES\" 2>&1", $output, $code);

            return response()->json([
                'message' => "User '{$username}' assigned to database '{$database}' with privileges: " . $privilegeStr
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to assign user: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update user permissions
     */
    public function updatePermissions(Request $request)
    {
        try {
            $request->validate([
                'database' => 'required|string|max:64',
                'username' => 'required|string|max:32',
                'host' => 'nullable|string',
                'privileges' => 'required|array'
            ]);

            $database = $request->input('database');
            $username = $request->input('username');
            $host = $request->input('host', 'localhost');
            $privileges = $request->input('privileges');

            // Revoke all existing privileges on this database
            $escapedUser = $this->escapeIdentifier($username);
            $escapedHost = $this->escapeString($host);
            DB::statement("REVOKE ALL PRIVILEGES ON `{$database}`.* FROM {$escapedUser}@{$escapedHost}");

            // Grant new privileges
            if (!empty($privileges)) {
                $allowedPrivileges = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'ALTER', 'INDEX', 'CREATE TEMPORARY TABLES', 'LOCK TABLES', 'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE', 'ALTER ROUTINE', 'EVENT', 'TRIGGER'];
                $privileges = array_intersect($privileges, $allowedPrivileges);
                
                if (!empty($privileges)) {
                    $privilegeStr = implode(', ', $privileges);
                    DB::statement("GRANT {$privilegeStr} ON `{$database}`.* TO {$escapedUser}@{$escapedHost}");
                }
            }

            DB::statement("FLUSH PRIVILEGES");

            return response()->json([
                'message' => "Permissions updated for user '{$username}' on database '{$database}'"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to update permissions: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:32',
                'host' => 'nullable|string',
                'password' => 'required|string|min:8'
            ]);

            $username = $request->input('username');
            $host = $request->input('host', 'localhost');
            $password = $request->input('password');

            $escapedUser = $this->escapeIdentifier($username);
            $escapedHost = $this->escapeString($host);
            $escapedPass = $this->escapeString($password);
            DB::statement("ALTER USER {$escapedUser}@{$escapedHost} IDENTIFIED BY {$escapedPass}");
            DB::statement("FLUSH PRIVILEGES");

            return response()->json([
                'message' => "Password updated for user '{$username}'@'{$host}'"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to update password: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get phpMyAdmin access URL for a specific database (with auto-login token)
     */
    public function getPhpMyAdminUrl(Request $request)
    {
        try {
            $request->validate([
                'database' => 'required|string|max:64',
                'username' => 'required|string|max:32',
                'host' => 'nullable|string'
            ]);

            $database = $request->input('database');
            $username = $request->input('username');
            $host = $request->input('host', 'localhost');
            
            // Generate a secure one-time token
            $token = Str::random(64);
            
            // Ensure token directory exists
            $tokenDir = storage_path('app/pma_tokens');
            if (!is_dir($tokenDir)) {
                mkdir($tokenDir, 0755, true);
            }
            
            // Get MySQL credentials from .env (for the nimbus admin user)
            // For security, we use the configured nimbus MySQL user
            $mysqlUser = config('database.connections.mysql.username');
            $mysqlPass = config('database.connections.mysql.password');
            
            // Store token data in file
            $tokenData = [
                'username' => $mysqlUser,
                'password' => $mysqlPass,
                'host' => $host,
                'database' => $database,
                'created' => time(),
                'panel_user' => auth()->user()->email ?? 'unknown'
            ];
            
            file_put_contents($tokenDir . '/' . $token . '.json', json_encode($tokenData));

            // Return URL to signon script
            return response()->json([
                'url' => "/pma_signon.php?token={$token}&db=" . urlencode($database),
                'username' => $mysqlUser,
                'database' => $database,
                'message' => "Opening phpMyAdmin for database '{$database}'"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get phpMyAdmin URL: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * phpMyAdmin signon - legacy redirect (now handled by pma_signon.php)
     */
    public function phpMyAdminSignon($token)
    {
        // Redirect to the new signon script
        return redirect("/pma_signon.php?token={$token}");
    }

    /**
     * phpMyAdmin view page (authenticated)
     */
    public function phpMyAdminView(Request $request)
    {
        return Inertia::render('Database/PhpMyAdmin', [
            'database' => $request->query('db', '')
        ]);
    }

    /**
     * Get list of all MySQL users
     */
    public function getUsers()
    {
        try {
            // Use sudo mysql to query mysql.user table (nimbus user doesn't have permission)
            $output = [];
            exec("sudo mysql -N -e \"SELECT User, Host FROM mysql.user WHERE User != '' AND User NOT LIKE 'mysql.%'\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to query users: " . implode("\n", $output));
            }
            
            $result = [];
            foreach ($output as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 2) {
                    $username = $parts[0];
                    $host = $parts[1];
                    
                    // Skip system users and nimbus internal users
                    $systemUsers = ['root', 'debian-sys-maint', 'mariadb.sys', 'nimbus', 'nimbus_admin', 'phpmyadmin', 'roundcube'];
                    if (in_array($username, $systemUsers)) continue;
                    
                    $result[] = [
                        'username' => $username,
                        'host' => $host
                    ];
                }
            }

            return response()->json(['users' => $result]);
        } catch (\Exception $e) {
            \Log::error("Failed to get users: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Configure nginx for phpMyAdmin
     */
    private function configurePhpMyAdminNginx()
    {
        // Detect PHP version dynamically
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        
        $config = <<<NGINX
# phpMyAdmin configuration
location /phpmyadmin {
    alias /usr/share/phpmyadmin;
    index index.php;

    location ~ ^/phpmyadmin/(.+\.php)$ {
        alias /usr/share/phpmyadmin/\$1;
        fastcgi_pass unix:/var/run/php/php{$phpVersion}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$request_filename;
    }

    location ~* ^/phpmyadmin/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ {
        alias /usr/share/phpmyadmin/\$1;
    }
}
NGINX;

        // Write to nginx snippets
        $snippetPath = '/etc/nginx/snippets/phpmyadmin.conf';
        $tempPath = '/tmp/phpmyadmin_nginx_' . time() . '.conf';
        
        file_put_contents($tempPath, $config);
        $this->executeSudoCommand("mv {$tempPath} {$snippetPath}");
        $this->executeSudoCommand("chmod 644 {$snippetPath}");

        // Create snippets directory if not exists
        $this->executeSudoCommand("mkdir -p /etc/nginx/snippets");

        // Add include to Nimbus nginx config if not already present
        $nimbusConfig = '/etc/nginx/sites-available/nimbus';
        if (file_exists($nimbusConfig)) {
            $content = file_get_contents($nimbusConfig);
            if (strpos($content, 'phpmyadmin.conf') === false) {
                // Add include before the last closing brace
                $this->executeSudoCommand("sed -i '/^}/i\\    include snippets/phpmyadmin.conf;' {$nimbusConfig}");
            }
        }
        
        // Reload nginx
        $this->executeSudoCommand("nginx -t && systemctl reload nginx");
    }

    /**
     * Create MySQL user helper
     */
    private function createMySQLUser($username, $password, $isAdmin = false)
    {
        try {
            // Use direct MySQL connection via exec instead of Laravel DB
            // This avoids issues when Laravel is configured for SQLite
            $escapedUser = escapeshellarg($username);
            $escapedPass = escapeshellarg($password);
            
            // Create user using mysql command
            exec("sudo mysql -e \"CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY '{$password}';\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                throw new \Exception("Failed to create user: " . implode("\n", $output));
            }
            
            if ($isAdmin) {
                exec("sudo mysql -e \"GRANT ALL PRIVILEGES ON *.* TO '{$username}'@'localhost' WITH GRANT OPTION;\" 2>&1", $output, $code);
            }
            
            exec("sudo mysql -e \"FLUSH PRIVILEGES;\" 2>&1", $output, $code);
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to create MySQL user: " . $e->getMessage());
        }
    }

    /**
     * Escape identifier for MySQL (backticks)
     */
    private function escapeIdentifier($value)
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * Escape string value for MySQL (quotes)
     */
    private function escapeString($value)
    {
        return "'" . addslashes($value) . "'";  
    }

    /**
     * Execute sudo command
     */
    private function executeSudoCommand($command)
    {
        $output = [];
        $returnCode = 0;
        exec("sudo $command 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Command failed: " . implode("\n", $output));
        }
        
        return $output;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
