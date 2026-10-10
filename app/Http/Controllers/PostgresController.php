<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\NimbusDatabase;
use PDO;

class PostgresController extends Controller
{
    private string $credentialsPath;
    private string $logPath;
    private string $statusPath;
    private string $lockPath;

    public function __construct()
    {
        $this->credentialsPath = storage_path('app/nimbus_postgres_credentials.json');
        $this->logPath = storage_path('logs/nimbus_postgres_install.log');
        $this->statusPath = storage_path('logs/nimbus_postgres_status.txt');
        $this->lockPath = storage_path('logs/nimbus_postgres_install.lock');
    }

    /**
     * Check if PostgreSQL is installed and its current status
     */
    public function getStatus()
    {
        try {
            $isInstalled = $this->isPostgresInstalled();
            $isActive = false;
            $version = 'Unknown';
            $dbCount = 0;
            $userCount = 0;

            if ($isInstalled) {
                exec("sudo systemctl is-active postgresql 2>/dev/null", $actOut, $actCode);
                $isActive = (isset($actOut[0]) && trim($actOut[0]) === 'active');

                exec("psql --version 2>/dev/null", $verOut);
                if (!empty($verOut[0]) && preg_match('/psql \(PostgreSQL\) ([0-9.]+)/', $verOut[0], $vM)) {
                    $version = $vM[1];
                }

                if ($isActive) {
                    try {
                        $dbs = $this->queryPostgres("SELECT COUNT(*) FROM pg_database WHERE datistemplate = false AND datname NOT IN ('postgres', 'template0', 'template1');");
                        $dbCount = (int)($dbs[0][0] ?? 0);

                        $users = $this->queryPostgres("SELECT COUNT(*) FROM pg_roles WHERE rolname NOT LIKE 'pg_%' AND rolname NOT IN ('postgres');");
                        $userCount = (int)($users[0][0] ?? 0);
                    } catch (\Exception $e) {
                        // ignore query error if permissions not ready
                    }
                }
            }

            $installStatus = 'idle';
            if (file_exists($this->statusPath)) {
                $installStatus = trim(file_get_contents($this->statusPath));
            }

            return response()->json([
                'success' => true,
                'installed' => $isInstalled,
                'active' => $isActive,
                'version' => $version,
                'port' => 5432,
                'database_count' => $dbCount,
                'user_count' => $userCount,
                'install_status' => $installStatus
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start background installation of PostgreSQL and php-pgsql
     */
    public function install(Request $request)
    {
        try {
            if ($this->isPostgresInstalled()) {
                return response()->json(['error' => 'PostgreSQL is already installed on this server.'], 400);
            }

            if (file_exists($this->lockPath)) {
                return response()->json(['error' => 'An installation is already in progress. Please wait.'], 409);
            }

            @file_put_contents($this->lockPath, 'PostgreSQL installation in progress');
            @file_put_contents($this->statusPath, 'running');
            @file_put_contents($this->logPath, "=== Starting PostgreSQL installation: " . date('Y-m-d H:i:s') . " ===\n");

            $adminPass = Str::random(18);

            $script = <<<'BASH'
#!/bin/bash
LOG_FILE="__LOG_FILE__"
STATUS_FILE="__STATUS_FILE__"
LOCK_FILE="__LOCK_FILE__"
CREDS_FILE="__CREDS_FILE__"
ADMIN_PASS="__ADMIN_PASS__"

cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

echo "Updating package lists..." >> "$LOG_FILE"
export DEBIAN_FRONTEND=noninteractive
sudo apt-get update >> "$LOG_FILE" 2>&1

echo "Installing PostgreSQL and PHP extensions (postgresql postgresql-contrib php-pgsql)..." >> "$LOG_FILE"
sudo apt-get install -y postgresql postgresql-contrib php-pgsql >> "$LOG_FILE" 2>&1

if ! which psql > /dev/null 2>&1; then
    echo "ERROR: PostgreSQL installation failed! psql binary not found." >> "$LOG_FILE"
    echo "error" > "$STATUS_FILE"
    exit 1
fi

echo "Enabling and starting PostgreSQL service..." >> "$LOG_FILE"
sudo systemctl enable postgresql >> "$LOG_FILE" 2>&1
sudo systemctl restart postgresql >> "$LOG_FILE" 2>&1

sleep 2

echo "Configuring nimbus_admin superuser..." >> "$LOG_FILE"
sudo -u postgres psql -c "DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'nimbus_admin') THEN
        CREATE ROLE nimbus_admin WITH SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD '${ADMIN_PASS}';
    ELSE
        ALTER ROLE nimbus_admin WITH SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD '${ADMIN_PASS}';
    END IF;
END
\$\$;" >> "$LOG_FILE" 2>&1

# Configure pg_hba.conf to allow local connections via md5 / scram-sha-256
HBA_FILE=$(sudo -u postgres psql -t -P format=unaligned -c "SHOW hba_file;" 2>/dev/null)
if [ -n "$HBA_FILE" ] && [ -f "$HBA_FILE" ]; then
    echo "Configuring pg_hba.conf at $HBA_FILE..." >> "$LOG_FILE"
    if ! sudo grep -q "127.0.0.1/32" "$HBA_FILE"; then
        echo "host    all             all             127.0.0.1/32            scram-sha-256" | sudo tee -a "$HBA_FILE" >> "$LOG_FILE"
    fi
    sudo systemctl reload postgresql >> "$LOG_FILE" 2>&1
fi

# Reload active PHP-FPM pools
for FPM in $(systemctl list-units --type=service --state=running | grep -o 'php[0-9.]*-fpm' | sort -u); do
    echo "Reloading $FPM for php-pgsql extension..." >> "$LOG_FILE"
    sudo systemctl reload "$FPM" >> "$LOG_FILE" 2>&1 || sudo systemctl restart "$FPM" >> "$LOG_FILE" 2>&1
done

echo "Saving credentials to $CREDS_FILE..." >> "$LOG_FILE"
cat << EOF > /tmp/nimbus_pg_creds.json
{
    "username": "nimbus_admin",
    "password": "${ADMIN_PASS}",
    "host": "127.0.0.1",
    "port": 5432,
    "created_at": "$(date '+%Y-%m-%d %H:%M:%S')"
}
EOF
sudo mv /tmp/nimbus_pg_creds.json "$CREDS_FILE"
sudo chown www-data:www-data "$CREDS_FILE"
sudo chmod 600 "$CREDS_FILE"

echo "=== PostgreSQL installation completed successfully! ===" >> "$LOG_FILE"
echo "completed" > "$STATUS_FILE"
BASH;

            $script = str_replace([
                '__LOG_FILE__',
                '__STATUS_FILE__',
                '__LOCK_FILE__',
                '__CREDS_FILE__',
                '__ADMIN_PASS__'
            ], [
                $this->logPath,
                $this->statusPath,
                $this->lockPath,
                $this->credentialsPath,
                $adminPass
            ], $script);

            $runnerPath = storage_path('app/run_pg_install.sh');
            file_put_contents($runnerPath, $script);
            chmod($runnerPath, 0755);

            // Execute script asynchronously
            exec("nohup bash {$runnerPath} > /dev/null 2>&1 &");

            return response()->json([
                'success' => true,
                'message' => 'PostgreSQL installation started in background.'
            ]);
        } catch (\Exception $e) {
            @unlink($this->lockPath);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get real-time status of PostgreSQL installer
     */
    public function getInstallStatus()
    {
        $status = 'idle';
        if (file_exists($this->statusPath)) {
            $status = trim(file_get_contents($this->statusPath));
        }

        $log = '';
        if (file_exists($this->logPath)) {
            $lines = file($this->logPath);
            $log = implode('', array_slice($lines, -60));
        }

        return response()->json([
            'status' => $status,
            'log' => $log
        ]);
    }

    /**
     * List all PostgreSQL databases
     */
    public function getDatabases()
    {
        try {
            if (!$this->isPostgresInstalled()) {
                return response()->json(['databases' => []]);
            }

            $sql = "SELECT 
                d.datname,
                pg_size_pretty(pg_database_size(d.datname)) as size,
                pg_database_size(d.datname) as size_bytes,
                pg_catalog.pg_get_userbyid(d.datdba) as owner,
                pg_catalog.pg_encoding_to_char(d.encoding) as encoding,
                d.datcollate as collation
            FROM pg_catalog.pg_database d
            WHERE d.datistemplate = false
              AND d.datname NOT IN ('postgres', 'template0', 'template1')
            ORDER BY d.datname;";

            $rows = $this->queryPostgres($sql);
            $databases = [];

            foreach ($rows as $row) {
                $name = $row[0] ?? '';
                if (empty($name)) continue;

                $size = $row[1] ?? '0 kB';
                $owner = $row[3] ?? 'postgres';
                $encoding = $row[4] ?? 'UTF8';
                $collation = $row[5] ?? '';

                // Get linked projects
                $meta = NimbusDatabase::where('name', $name)->first();
                $projects = [];
                if ($meta && !empty($meta->domain)) {
                    $projects[] = [
                        'project' => $meta->domain,
                        'type' => 'domain'
                    ];
                }

                $databases[] = [
                    'name' => $name,
                    'size' => $size,
                    'owner' => $owner,
                    'encoding' => $encoding,
                    'collation' => $collation,
                    'users' => [
                        ['username' => $owner]
                    ],
                    'projects' => $projects,
                    'created_by' => $meta ? $meta->created_by : 'System',
                    'created_at' => $meta && $meta->created_at ? $meta->created_at->format('Y-m-d H:i') : null
                ];
            }

            return response()->json([
                'success' => true,
                'databases' => $databases
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * List all PostgreSQL users / roles
     */
    public function getUsers()
    {
        try {
            if (!$this->isPostgresInstalled()) {
                return response()->json(['users' => []]);
            }

            $sql = "SELECT 
                r.rolname as username,
                r.rolsuper as is_superuser,
                r.rolcreatedb as can_create_db,
                r.rolcanlogin as can_login
            FROM pg_catalog.pg_roles r
            WHERE r.rolname NOT LIKE 'pg_%'
              AND r.rolname NOT IN ('postgres')
            ORDER BY r.rolname;";

            $rows = $this->queryPostgres($sql);
            $users = [];

            foreach ($rows as $row) {
                $username = $row[0] ?? '';
                if (empty($username)) continue;

                $users[] = [
                    'username' => $username,
                    'host' => 'localhost',
                    'is_superuser' => ($row[1] === 't' || $row[1] === true || $row[1] === '1'),
                    'can_create_db' => ($row[2] === 't' || $row[2] === true || $row[2] === '1'),
                    'can_login' => ($row[3] === 't' || $row[3] === true || $row[3] === '1'),
                ];
            }

            return response()->json([
                'success' => true,
                'users' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new PostgreSQL database
     */
    public function createDatabase(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/|max:63',
                'owner' => 'nullable|string|max:63'
            ]);

            $name = $request->input('name');
            $owner = $request->input('owner', 'nimbus_admin');

            // Sanitize
            $nameSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
            $ownerSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $owner);

            // Check if user exists, else fallback to nimbus_admin or postgres
            $checkUser = $this->queryPostgres("SELECT 1 FROM pg_roles WHERE rolname = '{$ownerSafe}';");
            if (empty($checkUser)) {
                $ownerSafe = 'nimbus_admin';
            }

            $sql = "CREATE DATABASE \"{$nameSafe}\" OWNER \"{$ownerSafe}\" ENCODING 'UTF8';";
            $this->executeSudoPsql($sql);

            NimbusDatabase::updateOrCreate(
                ['name' => $nameSafe],
                ['created_by' => auth()->user()->email ?? 'System']
            );

            return response()->json([
                'success' => true,
                'message' => "PostgreSQL database '{$nameSafe}' created successfully with owner '{$ownerSafe}'."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a PostgreSQL database
     */
    public function deleteDatabase(Request $request)
    {
        try {
            $request->validate(['name' => 'required|string|max:63']);
            $name = $request->input('name');
            $nameSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

            if (in_array(strtolower($nameSafe), ['postgres', 'template0', 'template1'])) {
                return response()->json(['error' => 'Cannot delete system database.'], 403);
            }

            // Terminate active connections first
            $termSql = "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$nameSafe}' AND pid <> pg_backend_pid();";
            $this->executeSudoPsql($termSql);

            // Drop database
            $this->executeSudoPsql("DROP DATABASE \"{$nameSafe}\";");

            NimbusDatabase::where('name', $nameSafe)->delete();

            return response()->json([
                'success' => true,
                'message' => "PostgreSQL database '{$nameSafe}' deleted successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new PostgreSQL user/role
     */
    public function createUser(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/|max:63',
                'password' => 'required|string|min:6',
                'can_create_db' => 'nullable|boolean',
                'is_superuser' => 'nullable|boolean'
            ]);

            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('username'));
            $password = addslashes($request->input('password'));

            $opts = "WITH LOGIN PASSWORD '{$password}'";
            if ($request->boolean('can_create_db')) {
                $opts .= " CREATEDB";
            }
            if ($request->boolean('is_superuser')) {
                $opts .= " SUPERUSER";
            }

            $sql = "CREATE ROLE \"{$username}\" {$opts};";
            $this->executeSudoPsql($sql);

            return response()->json([
                'success' => true,
                'message' => "PostgreSQL user '{$username}' created successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a PostgreSQL user/role
     */
    public function deleteUser(Request $request)
    {
        try {
            $request->validate(['username' => 'required|string|max:63']);
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('username'));

            if (in_array(strtolower($username), ['postgres', 'nimbus_admin'])) {
                return response()->json(['error' => 'Cannot delete system superuser role.'], 403);
            }

            // Drop owned objects or reassign to postgres
            $this->executeSudoPsql("DROP OWNED BY \"{$username}\";");
            $this->executeSudoPsql("DROP ROLE \"{$username}\";");

            return response()->json([
                'success' => true,
                'message' => "PostgreSQL user '{$username}' deleted successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update PostgreSQL user password
     */
    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:63',
                'password' => 'required|string|min:6'
            ]);

            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('username'));
            $password = addslashes($request->input('password'));

            $sql = "ALTER ROLE \"{$username}\" WITH PASSWORD '{$password}';";
            $this->executeSudoPsql($sql);

            return response()->json([
                'success' => true,
                'message' => "Password for user '{$username}' updated successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Assign user permissions to a database
     */
    public function assignUser(Request $request)
    {
        try {
            $request->validate([
                'database' => 'required|string|max:63',
                'username' => 'required|string|max:63'
            ]);

            $database = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('database'));
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('username'));

            // Grant connect and all privileges on database
            $this->executeSudoPsql("GRANT ALL PRIVILEGES ON DATABASE \"{$database}\" TO \"{$username}\";");

            // Grant schema privileges inside the database
            $schemaSql = "GRANT ALL ON SCHEMA public TO \"{$username}\"; GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO \"{$username}\"; ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO \"{$username}\";";
            $this->executeSudoPsql($schemaSql, $database);

            return response()->json([
                'success' => true,
                'message' => "User '{$username}' assigned to database '{$database}' with full privileges."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Link PostgreSQL database to project / domain
     */
    public function assignProject(Request $request)
    {
        try {
            $name = $request->input('name');
            $domain = $request->input('domain');

            $meta = NimbusDatabase::firstOrNew(['name' => $name]);
            $meta->domain = $domain ?: null;
            if (!$meta->exists) {
                $meta->created_by = auth()->user()->email ?? 'System';
            }
            $meta->save();

            return response()->json([
                'success' => true,
                'message' => $domain ? "Database linked to project '{$domain}'" : "Database project unlinked"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Control PostgreSQL service (start, stop, restart)
     */
    public function serviceControl(Request $request)
    {
        try {
            $action = $request->input('action');
            if (!in_array($action, ['start', 'stop', 'restart', 'reload'])) {
                return response()->json(['error' => 'Invalid service action.'], 400);
            }

            exec("sudo systemctl {$action} postgresql 2>&1", $output, $code);
            if ($code !== 0) {
                return response()->json(['error' => 'Failed to ' . $action . ' PostgreSQL: ' . implode("\n", $output)], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "PostgreSQL service {$action}ed successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Open Native Database Workspace (ManagerPage) for a PostgreSQL database
     */
    public function openManager(Request $request)
    {
        try {
            $database = $request->input('database');
            if (empty($database)) {
                return response()->json(['error' => 'Database name is required.'], 400);
            }

            $token = Str::random(64);
            $tokenDir = storage_path('app/db_tokens');
            if (!is_dir($tokenDir)) {
                mkdir($tokenDir, 0755, true);
            }

            $tokenData = [
                'token' => $token,
                'database' => $database,
                'engine' => 'postgres',
                'user_id' => auth()->id(),
                'user_email' => auth()->user()->email ?? 'unknown',
                'created_at' => time(),
                'expires_at' => time() + 900 // 15 mins
            ];

            file_put_contents("{$tokenDir}/{$token}.json", json_encode($tokenData));

            return response()->json([
                'success' => true,
                'url' => "/database/manager/view/{$token}",
                'database' => $database,
                'engine' => 'postgres'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check if psql binary exists on the system
     */
    private function isPostgresInstalled(): bool
    {
        $out = [];
        $code = 0;
        exec("which psql 2>/dev/null", $out, $code);
        return ($code === 0 && !empty($out[0]));
    }

    /**
     * Execute SQL query via sudo -u postgres psql and return 2D array of rows
     */
    private function queryPostgres(string $sql, string $db = 'postgres'): array
    {
        $sqlEscaped = str_replace("'", "'\\''", $sql);
        $cmd = "sudo -u postgres psql -d \"{$db}\" -t -A -F '|' -c '{$sqlEscaped}' 2>/dev/null";
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        if ($code !== 0) {
            return [];
        }

        $results = [];
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $results[] = explode('|', $line);
        }

        return $results;
    }

    /**
     * Execute sudo SQL command in PostgreSQL
     */
    private function executeSudoPsql(string $sql, string $db = 'postgres')
    {
        $sqlEscaped = str_replace("'", "'\\''", $sql);
        $cmd = "sudo -u postgres psql -d \"{$db}\" -c '{$sqlEscaped}' 2>&1";
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        if ($code !== 0) {
            throw new \Exception("PostgreSQL command failed: " . implode("\n", $output));
        }

        return $output;
    }
}
