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

            $output = [];
            $returnCode = 0;
            
            // Set debconf selections to avoid interactive prompts
            exec("echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | sudo debconf-set-selections 2>&1", $output, $returnCode);
            exec("echo 'phpmyadmin phpmyadmin/app-password-confirm password ' | sudo debconf-set-selections 2>&1", $output, $returnCode);
            exec("echo 'phpmyadmin phpmyadmin/mysql/admin-pass password ' | sudo debconf-set-selections 2>&1", $output, $returnCode);
            exec("echo 'phpmyadmin phpmyadmin/mysql/app-pass password ' | sudo debconf-set-selections 2>&1", $output, $returnCode);
            exec("echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect none' | sudo debconf-set-selections 2>&1", $output, $returnCode);
            
            // Update package cache first
            exec("sudo apt-get update 2>&1", $output, $returnCode);
            
            // Install phpMyAdmin - use env inside sudo to set DEBIAN_FRONTEND
            $output = [];
            exec("sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y phpmyadmin 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                return response()->json([
                    'error' => 'Failed to install phpMyAdmin',
                    'details' => implode("\n", $output)
                ], 500);
            }


            // Generate admin credentials
            $adminUser = 'nimbus_admin';
            $adminPass = Str::random(16);
            
            // Create MySQL admin user for phpMyAdmin
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
                'message' => 'phpMyAdmin installed successfully',
                'credentials' => $credentials,
                'showCredentials' => true
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to install phpMyAdmin: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
     * Get all databases with their users
     */
    public function getDatabases()
    {
        try {
            // Get all databases (excluding system databases)
            $databases = DB::select("SHOW DATABASES");
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];
            
            $result = [];
            
            foreach ($databases as $db) {
                $dbName = $db->Database;
                
                if (in_array($dbName, $systemDbs)) {
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
            
            // Check if database exists
            $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
            if (!empty($exists)) {
                return response()->json(['error' => 'Database already exists'], 400);
            }

            // Create database
            DB::statement("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

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
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];
            if (in_array($dbName, $systemDbs)) {
                return response()->json(['error' => 'Cannot delete system database'], 403);
            }

            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");

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

            // Check if user exists
            $exists = DB::select("SELECT User FROM mysql.user WHERE User = ? AND Host = ?", [$username, $host]);
            if (!empty($exists)) {
                return response()->json(['error' => 'User already exists'], 400);
            }

            // Create user - use raw SQL with proper escaping (DDL doesn't support prepared statements)
            $escapedUser = $this->escapeIdentifier($username);
            $escapedHost = $this->escapeString($host);
            $escapedPass = $this->escapeString($password);
            DB::statement("CREATE USER {$escapedUser}@{$escapedHost} IDENTIFIED BY {$escapedPass}");

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

            $escapedUser = $this->escapeIdentifier($username);
            $escapedHost = $this->escapeString($host);
            DB::statement("DROP USER IF EXISTS {$escapedUser}@{$escapedHost}");

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
            
            // Grant privileges
            $escapedUser = $this->escapeIdentifier($username);
            $escapedHost = $this->escapeString($host);
            DB::statement("GRANT {$privilegeStr} ON `{$database}`.* TO {$escapedUser}@{$escapedHost}");
            DB::statement("FLUSH PRIVILEGES");

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
            $users = DB::select("SELECT User, Host FROM mysql.user WHERE User != '' AND User NOT LIKE 'mysql.%'");
            
            $result = [];
            foreach ($users as $user) {
                if ($user->User === 'root' || $user->User === 'debian-sys-maint') continue;
                
                $result[] = [
                    'username' => $user->User,
                    'host' => $user->Host
                ];
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
        $config = <<<'NGINX'
# phpMyAdmin configuration
location /phpmyadmin {
    alias /usr/share/phpmyadmin;
    index index.php;
    
    location ~ ^/phpmyadmin/(.+\.php)$ {
        alias /usr/share/phpmyadmin/$1;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
    }
    
    location ~* ^/phpmyadmin/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ {
        alias /usr/share/phpmyadmin/$1;
    }
}
NGINX;

        // Write to nginx snippets
        $snippetPath = '/etc/nginx/snippets/phpmyadmin.conf';
        $tempPath = '/tmp/phpmyadmin_nginx_' . time() . '.conf';
        
        file_put_contents($tempPath, $config);
        $this->executeSudoCommand("mv {$tempPath} {$snippetPath}");
        $this->executeSudoCommand("chmod 644 {$snippetPath}");

        // Add include to Nimbus nginx config if not already present
        $nimbusConfig = '/etc/nginx/sites-available/nimbus';
        if (file_exists($nimbusConfig)) {
            $output = [];
            exec("sudo grep -l 'phpmyadmin.conf' {$nimbusConfig} 2>&1", $output);
            if (empty($output)) {
                // Add include before the last closing brace
                $this->executeSudoCommand("sudo sed -i '/^}/i\\    include snippets/phpmyadmin.conf;' {$nimbusConfig}");
            }
        }
    }

    /**
     * Create MySQL user helper
     */
    private function createMySQLUser($username, $password, $isAdmin = false)
    {
        try {
            $escapedUser = $this->escapeIdentifier($username);
            $escapedPass = $this->escapeString($password);
            
            DB::statement("CREATE USER IF NOT EXISTS {$escapedUser}@'localhost' IDENTIFIED BY {$escapedPass}");
            
            if ($isAdmin) {
                DB::statement("GRANT ALL PRIVILEGES ON *.* TO {$escapedUser}@'localhost' WITH GRANT OPTION");
            }
            
            DB::statement("FLUSH PRIVILEGES");
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
