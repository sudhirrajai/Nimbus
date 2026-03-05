<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DatabaseController extends Controller
{
    private $viewerPath = '/usr/share/adminer';
    private $adminerPublicPath = null;
    private $credentialsPath = '/usr/local/nimbus/storage/app/phpmyadmin_credentials.json';

    public function __construct() { $this->adminerPublicPath = public_path('adminer'); }

    /**
     * Display database management page
     */
    public function index()
    {
        return Inertia::render('Database/Index');
    }

    /**
     * Get Database Viewer installation status
     */
    public function getStatus()
    {
        try {
            $isInstalled = file_exists($this->viewerPath . '/adminer.php') && file_exists($this->adminerPublicPath . '/index.php');
            $hasCredentials = file_exists($this->credentialsPath);
            
            return response()->json([
                'viewerInstalled' => $isInstalled,
                'credentialsSet' => $hasCredentials,
                'firstTimeSetup' => $isInstalled && !$hasCredentials
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get database status: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Install Database Viewer (no apt — just downloads a single PHP file)
     */
    public function installPhpMyAdmin()
    {
        try {
            if (file_exists($this->viewerPath . '/adminer.php') && file_exists($this->adminerPublicPath . '/index.php')) {
                return response()->json(['error' => 'Database Viewer is already installed'], 400);
            }
            $lockFile = storage_path('logs/nimbus_install.lock');
            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json(['error' => "Another installation is in progress: {$lockContent}. Please wait."], 409);
            }
            file_put_contents($lockFile, 'Database Viewer installation');
            $logFile    = storage_path('logs/phpmyadmin_install.log');
            $statusFile = storage_path('logs/phpmyadmin_status.txt');
            file_put_contents($logFile,    "Database Viewer installation started at " . date('Y-m-d H:i:s') . "\n");
            file_put_contents($statusFile, 'running');
            $adminUser = 'nimbus_admin';
            $adminPass = Str::random(16);
            $this->createDatabase ViewerWrapper();
            $scriptLockFile   = $lockFile;
            $scriptLogFile    = $logFile;
            $scriptStatusFile = $statusFile;
            $script = <<<BASH
#!/bin/bash
LOG_FILE="{$scriptLogFile}"
STATUS_FILE="{$scriptStatusFile}"
LOCK_FILE="{$scriptLockFile}"
cleanup() { rm -f "\$LOCK_FILE"; }
trap cleanup EXIT
ADMINER_STORE="/usr/share/adminer"
ADMINER_VERSION="4.8.1"
GH_URL="https://github.com/vrana/adminer/releases/download/v\${ADMINER_VERSION}/adminer-\${ADMINER_VERSION}.php"
ALT_URL="https://www.adminer.org/static/download/\${ADMINER_VERSION}/adminer-\${ADMINER_VERSION}.php"
echo "Creating Database Viewer directory..."; sudo mkdir -p "\$ADMINER_STORE"
echo "Downloading Database Viewer \${ADMINER_VERSION}..."
sudo curl -fsSL "\$GH_URL" -o "\$ADMINER_STORE/adminer.php" 2>&1
if [ \$? -ne 0 ] || [ ! -f "\$ADMINER_STORE/adminer.php" ]; then
    echo "Primary failed, trying adminer.org..."
    sudo curl -fsSL "\$ALT_URL" -o "\$ADMINER_STORE/adminer.php" 2>&1
fi
if [ ! -f "\$ADMINER_STORE/adminer.php" ]; then
    echo "ERROR: Failed to download Database Viewer!"; echo "error" > "\$STATUS_FILE"; exit 1
fi
echo "Setting permissions..."
sudo chown -R www-data:www-data "\$ADMINER_STORE"; sudo chmod 755 "\$ADMINER_STORE"; sudo chmod 644 "\$ADMINER_STORE/adminer.php"
echo "Creating MySQL admin user..."
sudo mysql -e "DROP USER IF EXISTS '{$adminUser}'@'localhost'" 2>&1 || true
sudo mysql -e "CREATE USER '{$adminUser}'@'localhost' IDENTIFIED BY '{$adminPass}'" 2>&1
sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO '{$adminUser}'@'localhost' WITH GRANT OPTION" 2>&1
sudo mysql -e "FLUSH PRIVILEGES" 2>&1
echo "Installation completed successfully; echo "Username: {$adminUser}"; echo "Database Viewer: Ready at /adminer/"
echo "done" > "\$STATUS_FILE"
BASH;
            $tempScript = '/tmp/adminer_install.sh';
            file_put_contents($tempScript, $script);
            chmod($tempScript, 0755);
            exec("sudo bash {$tempScript} >> {$logFile} 2>&1 &");
            $credentials = ['username' => $adminUser, 'password' => $adminPass, 'created_at' => now()->toDateTimeString(), 'url' => '/adminer/'];
            $credentialsDir = dirname($this->credentialsPath);
            if (!File::exists($credentialsDir)) { File::makeDirectory($credentialsDir, 0755, true); }
            File::put($this->credentialsPath, json_encode($credentials, JSON_PRETTY_PRINT));
            return response()->json(['message' => 'Database Viewer installation started...', 'credentials' => $credentials, 'polling' => true]);
        } catch (\Exception $e) {
            \Log::error("Failed to install Database Viewer: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Database Viewer install status / log
     */
    public function getInstallStatus()
    {
        $logFile    = storage_path('logs/phpmyadmin_install.log');
        $statusFile = storage_path('logs/phpmyadmin_status.txt');
        $log    = file_exists($logFile)    ? file_get_contents($logFile)    : '';
        $status = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'idle';
        if ($status === 'running') {
            $adminerReady = file_exists($this->viewerPath . '/adminer.php');
            if (strpos($log, 'Installation completed successfully') !== false) {
                file_put_contents($statusFile, 'done'); $status = 'done';
            } elseif (strpos($log, 'ERROR:') !== false) {
                file_put_contents($statusFile, 'error'); $status = 'error';
            } elseif ($adminerReady && (time() - filemtime($logFile) > 5)) {
                file_put_contents($statusFile, 'done'); $status = 'done';
            }
        }
        $isInstalled = file_exists($this->viewerPath . '/adminer.php') && file_exists($this->adminerPublicPath . '/index.php');
        return response()->json(['status' => $status, 'log' => $log, 'installed' => $isInstalled]);
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
            
            $content = "Database Viewer Credentials\n";
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
     * Reinstall Database Viewer (remove and install again)
     */
    public function reinstallPhpMyAdmin()
    {
        try {
            // Remove existing Database Viewer files
            $adminerFile = $this->viewerPath . '/adminer.php';
            if (file_exists($adminerFile)) {
                $this->executeSudoCommand("rm -f {$adminerFile}");
            }
            $wrapperFile = $this->adminerPublicPath . '/index.php';
            if (file_exists($wrapperFile)) {
                unlink($wrapperFile);
            }

            // Remove credentials file
            if (file_exists($this->credentialsPath)) {
                unlink($this->credentialsPath);
            }

            // Generate new credentials
            $adminUser = 'nimbus_admin';
            $adminPass = Str::random(16);

            // Re-create SSO wrapper
            $this->createDatabase ViewerWrapper();

            // Re-download Database Viewer + recreate MySQL user
            $logFile    = storage_path('logs/phpmyadmin_install.log');
            $statusFile = storage_path('logs/phpmyadmin_status.txt');
            $lockFile   = storage_path('logs/nimbus_install.lock');
            file_put_contents($logFile,    "Database Viewer reinstall started at " . date('Y-m-d H:i:s') . "\n");
            file_put_contents($statusFile, 'running');
            file_put_contents($lockFile,   'Database Viewer reinstall');

            $scriptLockFile   = $lockFile;
            $scriptLogFile    = $logFile;
            $scriptStatusFile = $statusFile;

            $script = <<<BASH
#!/bin/bash
LOG_FILE="{$scriptLogFile}"; STATUS_FILE="{$scriptStatusFile}"; LOCK_FILE="{$scriptLockFile}"
cleanup() { rm -f "\$LOCK_FILE"; }; trap cleanup EXIT
ADMINER_STORE="/usr/share/adminer"; ADMINER_VERSION="4.8.1"
GH_URL="https://github.com/vrana/adminer/releases/download/v\${ADMINER_VERSION}/adminer-\${ADMINER_VERSION}.php"
ALT_URL="https://www.adminer.org/static/download/\${ADMINER_VERSION}/adminer-\${ADMINER_VERSION}.php"
echo "Downloading Database Viewer \${ADMINER_VERSION}..."; sudo mkdir -p "\$ADMINER_STORE"
sudo curl -fsSL "\$GH_URL" -o "\$ADMINER_STORE/adminer.php" 2>&1
if [ \$? -ne 0 ] || [ ! -f "\$ADMINER_STORE/adminer.php" ]; then sudo curl -fsSL "\$ALT_URL" -o "\$ADMINER_STORE/adminer.php" 2>&1; fi
if [ ! -f "\$ADMINER_STORE/adminer.php" ]; then echo "ERROR: Download failed!"; echo "error" > "\$STATUS_FILE"; exit 1; fi
sudo chown -R www-data:www-data "\$ADMINER_STORE"; sudo chmod 644 "\$ADMINER_STORE/adminer.php"
echo "Recreating MySQL admin user..."
sudo mysql -e "DROP USER IF EXISTS '{$adminUser}'@'localhost'" 2>&1 || true
sudo mysql -e "CREATE USER '{$adminUser}'@'localhost' IDENTIFIED BY '{$adminPass}'" 2>&1
sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO '{$adminUser}'@'localhost' WITH GRANT OPTION" 2>&1
sudo mysql -e "FLUSH PRIVILEGES" 2>&1
echo "Installation completed successfully!"; echo "done" > "\$STATUS_FILE"
BASH;
            $tempScript = '/tmp/adminer_reinstall.sh';
            file_put_contents($tempScript, $script);
            chmod($tempScript, 0755);
            exec("sudo bash {$tempScript} >> {$logFile} 2>&1 &");

            // Save credentials
            $credentials = [
                'username'   => $adminUser,
                'password'   => $adminPass,
                'created_at' => now()->toDateTimeString(),
                'url'        => '/adminer/'
            ];
            $credentialsDir = dirname($this->credentialsPath);
            if (!File::exists($credentialsDir)) { File::makeDirectory($credentialsDir, 0755, true); }
            File::put($this->credentialsPath, json_encode($credentials, JSON_PRETTY_PRINT));

            return response()->json([
                'message'         => 'Database Viewer reinstall started...',
                'credentials'     => $credentials,
                'showCredentials' => true,
                'polling'         => true
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to reinstall Database Viewer: " . $e->getMessage());
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
            // Use sudo mysql to query users with access to this database
            $escapedDb = escapeshellarg($dbName);
            $escapedDbWildcard = escapeshellarg(str_replace('_', '\\_', $dbName));
            
            $output = [];
            exec("sudo mysql -N -e \"SELECT DISTINCT User, Host FROM mysql.db WHERE Db = {$escapedDb} OR Db = {$escapedDbWildcard}\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                return [];
            }

            $result = [];
            foreach ($output as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 2) {
                    $user = $parts[0];
                    $host = $parts[1];
                    
                    if ($user === 'root' || $user === '' || $user === 'nimbus_admin') continue;
                    
                    $privileges = $this->getUserPrivileges($user, $host, $dbName);
                    $result[] = [
                        'username' => $user,
                        'host' => $host,
                        'privileges' => $privileges
                    ];
                }
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
            $escapedUser = escapeshellarg($username);
            $escapedHost = escapeshellarg($host);
            
            $output = [];
            exec("sudo mysql -N -e \"SHOW GRANTS FOR {$escapedUser}@{$escapedHost}\" 2>&1", $output, $code);
            
            if ($code !== 0) {
                return [];
            }
            
            $privileges = [];
            
            foreach ($output as $grantStr) {
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

            // Grant access to nimbus_admin (Database Viewer user) so database shows in Database Viewer
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
     * Get Database Viewer access URL for a specific database (with auto-login SSO token)
     */
    public function getDatabaseViewerUrl(Request $request)
    {
        try {
            $request->validate([
                'database' => 'required|string|max:64',
                'username' => 'nullable|string|max:32',
                'host'     => 'nullable|string'
            ]);

            $database = $request->input('database');
            $host     = $request->input('host', 'localhost');

            // Use nimbus_admin credentials (has ALL PRIVILEGES - can open any DB)
            // Fall back to nimbus DB user if credentials file not found
            if (file_exists($this->credentialsPath)) {
                $credentials = json_decode(File::get($this->credentialsPath), true);
                $mysqlUser   = $credentials['username'] ?? config('database.connections.mysql.username');
                $mysqlPass   = $credentials['password'] ?? config('database.connections.mysql.password');
            } else {
                $mysqlUser = config('database.connections.mysql.username');
                $mysqlPass = config('database.connections.mysql.password');
            }

            // Generate a secure one-time token
            $token = Str::random(64);

            // Ensure token directory exists
            $tokenDir = storage_path('app/pma_tokens');
            if (!is_dir($tokenDir)) {
                mkdir($tokenDir, 0755, true);
            }

            // Store token data
            $tokenData = [
                'username'   => $mysqlUser,
                'password'   => $mysqlPass,
                'host'       => $host,
                'database'   => $database,
                'created'    => time(),
                'panel_user' => auth()->user()->email ?? 'unknown'
            ];

            file_put_contents($tokenDir . '/' . $token . '.json', json_encode($tokenData));

            // Return SSO URL
            return response()->json([
                'url'      => "/pma_signon.php?token={$token}&db=" . urlencode($database),
                'database' => $database,
                'message'  => "Opening Database Viewer for database '{$database}'"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get Database Viewer URL: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Database Viewer signon - legacy redirect (now handled by pma_signon.php)
     */
    public function databaseViewerSignon($token)
    {
        // Redirect to the new signon script
        return redirect("/pma_signon.php?token={$token}");
    }

    /**
     * Open Database Viewer with SSO (auto-login with nimbus_admin)
     */
    public function openDatabaseViewerSSO()
    {
        try {
            // Read credentials from file
            if (!file_exists($this->credentialsPath)) {
                return response()->json(['error' => 'Database Viewer credentials not found. Please reinstall it.'], 404);
            }
            
            $credentials = json_decode(File::get($this->credentialsPath), true);
            if (!$credentials || empty($credentials['username']) || empty($credentials['password'])) {
                return response()->json(['error' => 'Invalid Database Viewer credentials'], 500);
            }
            
            // Generate a secure one-time token
            $token = Str::random(64);
            
            // Ensure token directory exists
            $tokenDir = storage_path('app/pma_tokens');
            if (!is_dir($tokenDir)) {
                mkdir($tokenDir, 0755, true);
            }
            
            // Store token data
            $tokenData = [
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'host' => 'localhost',
                'database' => '',
                'created' => time(),
                'panel_user' => auth()->user()->email ?? 'unknown'
            ];
            
            file_put_contents($tokenDir . '/' . $token . '.json', json_encode($tokenData));

            // Return the SSO URL
            return response()->json([
                'success' => true,
                'url' => "/pma_signon.php?token={$token}"
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to generate Database Viewer SSO: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Database Viewer view page (authenticated)
     */
    public function Database ViewerView(Request $request)
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
     * Configure nginx for Database Viewer
     */
    private function configurePhpMyAdminNginx()
    {
        // Detect PHP version dynamically
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        
        $config = <<<NGINX
# Database Viewer configuration
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
     * Apply SSO-only config for Database Viewer
     * Disables the login form - users must come via panel token
     */
    private function applyPhpMyAdminSSOConfig()
    {
        $ssoConfig = <<<'PHP'
<?php
/**
 * Nimbus SSO Configuration for Database Viewer
 * Forces token-only login via Nimbus panel.
 * Direct username/password login is disabled.
 */

// Use signon authentication - disables the login form
$cfg['Servers'][1]['auth_type'] = 'signon';
$cfg['Servers'][1]['SignonSession'] = 'SignonSession';
$cfg['Servers'][1]['SignonURL']    = '/pma_signon.php';
$cfg['Servers'][1]['LogoutURL']    = '/database';

// Clear any hardcoded user/pass (force SSO only)
unset($cfg['Servers'][1]['user']);
unset($cfg['Servers'][1]['password']);

// Local host only
$cfg['Servers'][1]['host'] = 'localhost';
$cfg['Servers'][1]['AllowNoPassword'] = false;
PHP;

        $tempFile = '/tmp/nimbus_pma_sso_' . time() . '.php';
        file_put_contents($tempFile, $ssoConfig);

        // Try /etc/phpmyadmin/conf.d/ first (standard location)
        $confDir = '/etc/phpmyadmin/conf.d';
        $this->executeSudoCommand("mkdir -p {$confDir}");

        // Check if dir is writable/exists after creation
        $output = [];
        exec("test -d {$confDir} && echo 'exists'", $output);
        $targetDir = (!empty($output) && $output[0] === 'exists') ? $confDir : '/usr/share/phpmyadmin';

        $this->executeSudoCommand("cp {$tempFile} {$targetDir}/nimbus_sso.php");
        $this->executeSudoCommand("chmod 644 {$targetDir}/nimbus_sso.php");

        unlink($tempFile);
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
     * Create the Database Viewer SSO Wrapper (public/adminer/index.php)
     */
    private function createDatabase ViewerWrapper()
    {
        if (!file_exists($this->adminerPublicPath)) {
            mkdir($this->adminerPublicPath, 0755, true);
        }

        $wrapperPath = $this->adminerPublicPath . '/index.php';
        
        $content = <<<'PHP'
<?php
/**
 * Database Viewer SSO Wrapper — Nimbus Panel
 * This file auto-connects to the database using session credentials.
 */
@session_start();

// 1. Basic Auth Check
if (empty($_SESSION['adminer_username'])) {
    header('Location: /database?notice=unauthorized');
    exit;
}

// 2. Timeout check (token validity)
if (isset($_SESSION['adminer_created']) && (time() - $_SESSION['adminer_created'] > 300)) {
    // Session expired for this specific signon
    unset($_SESSION['adminer_username'], $_SESSION['adminer_password'], $_SESSION['adminer_server']);
    header('Location: /database?notice=session_expired');
    exit;
}

// 3. Database Viewer Autologin Implementation
function adminer_object()
{
    // Credentials from session (set by pma_signon.php)
    $_nimbus_server   = $_SESSION['adminer_server']   ?? 'localhost';
    $_nimbus_username = $_SESSION['adminer_username'];
    $_nimbus_password = $_SESSION['adminer_password'];
    $_nimbus_db       = $_SESSION['adminer_db']       ?? '';

    class Database ViewerNimbus extends Database Viewer
    {
        private $server, $username, $password, $db;

        function __construct($server, $username, $password, $db)
        {
            $this->server   = $server;
            $this->username = $username;
            $this->password = $password;
            $this->db       = $db;
        }

        // Disable login screen entirely
        function name() { return 'Nimbus Database Manager'; }
        
        // Auto-login
        function credentials() {
            return [$this->server, $this->username, $this->password];
        }

        function database() {
            return $this->db;
        }
    }

    return new Database ViewerNimbus($_nimbus_server, $_nimbus_username, $_nimbus_password, $_nimbus_db);
}

// 4. Include the main Database Viewer script
include '/usr/share/adminer/adminer.php';
PHP;

        file_put_contents($wrapperPath, $content);
        chmod($wrapperPath, 0644);
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
