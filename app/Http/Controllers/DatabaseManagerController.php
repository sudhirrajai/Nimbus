<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;

class DatabaseManagerController extends Controller
{
    /**
     * System databases protected from unauthorized access / deletion
     */
    private array $protectedDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'nimbus'];

    /**
     * Check if current user can access the database
     */
    private function checkDatabaseAccess(string $dbName): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isRoot()) return true;

        if (in_array(strtolower($dbName), $this->protectedDbs)) {
            return false;
        }

        return $user->canAccessDatabase($dbName);
    }

    /**
     * Get valid MySQL credentials or auto-provision nimbus_admin user if needed
     */
    private function getValidMysqlCredentials(): array
    {
        $credentialsPath = storage_path('app/nimbus_db_credentials.json');

        // 1. Check existing saved nimbus_admin credentials
        if (file_exists($credentialsPath)) {
            $data = json_decode(@file_get_contents($credentialsPath), true);
            if (!empty($data['username']) && !empty($data['password'])) {
                return [
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'host' => '127.0.0.1',
                    'port' => '3306'
                ];
            }
        }

        // 2. Check Debian/Ubuntu system maintenance file /etc/mysql/debian.cnf
        if (file_exists('/etc/mysql/debian.cnf')) {
            $cnfContent = @file_get_contents('/etc/mysql/debian.cnf');
            if (preg_match('/user\s*=\s*(.+)/', $cnfContent, $mUser) && preg_match('/password\s*=\s*(.+)/', $cnfContent, $mPass)) {
                $user = trim($mUser[1]);
                $pass = trim($mPass[1]);
                if (!empty($user) && !empty($pass)) {
                    return [
                        'username' => $user,
                        'password' => $pass,
                        'host' => '127.0.0.1',
                        'port' => '3306'
                    ];
                }
            }
        }

        // 3. Check Laravel database configuration if password is set
        $envUser = config('database.connections.mysql.username', 'root');
        $envPass = config('database.connections.mysql.password', '');
        if (!empty($envPass)) {
            return [
                'username' => $envUser,
                'password' => $envPass,
                'host' => config('database.connections.mysql.host', '127.0.0.1'),
                'port' => config('database.connections.mysql.port', '3306')
            ];
        }

        // 4. Auto-provision nimbus_admin user on server via sudo mysql
        try {
            $adminUser = 'nimbus_admin';
            $adminPass = Str::random(16);

            $cmd = "sudo mysql -e \"DROP USER IF EXISTS '{$adminUser}'@'localhost'; CREATE USER '{$adminUser}'@'localhost' IDENTIFIED BY '{$adminPass}'; GRANT ALL PRIVILEGES ON *.* TO '{$adminUser}'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;\" 2>&1";
            exec($cmd, $out, $code);

            if ($code === 0 || file_exists($credentialsPath)) {
                $creds = [
                    'username' => $adminUser,
                    'password' => $adminPass,
                    'created_at' => now()->toDateTimeString(),
                    'url' => '/db/'
                ];
                $dir = dirname($credentialsPath);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($credentialsPath, json_encode($creds, JSON_PRETTY_PRINT));

                return [
                    'username' => $adminUser,
                    'password' => $adminPass,
                    'host' => '127.0.0.1',
                    'port' => '3306'
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Auto-provisioning nimbus_admin user failed: " . $e->getMessage());
        }

        // 5. Fallback default
        return [
            'username' => $envUser,
            'password' => $envPass,
            'host' => config('database.connections.mysql.host', '127.0.0.1'),
            'port' => config('database.connections.mysql.port', '3306')
        ];
    }

    /**
     * Helper to get a PDO instance connected to a specific database
     */
    private function getPdoConnection(string $dbName): PDO
    {
        $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        $creds = $this->getValidMysqlCredentials();

        $username = $creds['username'];
        $password = $creds['password'];
        $host = $creds['host'] ?? '127.0.0.1';
        $port = $creds['port'] ?? '3306';
        $socket = config('database.connections.mysql.unix_socket');

        try {
            if (!empty($socket) && file_exists($socket)) {
                $dsn = "mysql:dbname={$safeDb};unix_socket={$socket};charset=utf8mb4";
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$safeDb};charset=utf8mb4";
            }

            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            Log::warning("PDO connection as '{$username}' failed for DB '{$dbName}': " . $e->getMessage() . ". Attempting auto-provisioning.");

            // Attempt emergency auto-provisioning of nimbus_admin user
            try {
                $adminUser = 'nimbus_admin';
                $adminPass = Str::random(16);

                $cmd = "sudo mysql -e \"DROP USER IF EXISTS '{$adminUser}'@'localhost'; CREATE USER '{$adminUser}'@'localhost' IDENTIFIED BY '{$adminPass}'; GRANT ALL PRIVILEGES ON *.* TO '{$adminUser}'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;\" 2>&1";
                exec($cmd);

                $credentialsPath = storage_path('app/nimbus_db_credentials.json');
                $dir = dirname($credentialsPath);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($credentialsPath, json_encode([
                    'username' => $adminUser,
                    'password' => $adminPass,
                    'created_at' => now()->toDateTimeString(),
                    'url' => '/db/'
                ], JSON_PRETTY_PRINT));

                $dsn = "mysql:host={$host};port={$port};dbname={$safeDb};charset=utf8mb4";
                return new PDO($dsn, $adminUser, $adminPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (\Exception $provErr) {
                throw new \Exception("Database Connection Failed for '{$dbName}': " . $e->getMessage());
            }
        }
    }

    /**
     * Generate temporary single-use session token for opening DB Manager in a new tab
     */
    public function generateToken(Request $request)
    {
        try {
            $database = $request->input('database');
            if (empty($database)) {
                return response()->json(['error' => 'Database name is required'], 400);
            }

            if (!$this->checkDatabaseAccess($database)) {
                return response()->json(['error' => 'Permission denied for this database.'], 403);
            }

            $token = Str::random(64);
            $tokenDir = storage_path('app/db_tokens');
            if (!is_dir($tokenDir)) {
                mkdir($tokenDir, 0755, true);
            }

            $tokenData = [
                'token' => $token,
                'database' => $database,
                'user_id' => auth()->id(),
                'user_email' => auth()->user()->email ?? 'unknown',
                'created_at' => time(),
                'expires_at' => time() + 900 // 15 minutes validity
            ];

            file_put_contents("{$tokenDir}/{$token}.json", json_encode($tokenData));

            return response()->json([
                'success' => true,
                'token' => $token,
                'database' => $database,
                'url' => "/database/manager/view/{$token}"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Render full standalone Database Manager page for a given single-use token
     */
    public function viewPage(string $token)
    {
        $tokenPath = storage_path("app/db_tokens/{$token}.json");
        if (!file_exists($tokenPath)) {
            return redirect()->route('database.index')->with('error', 'Database session expired or invalid token.');
        }

        $tokenData = json_decode(file_get_contents($tokenPath), true);
        if (!$tokenData || (time() > ($tokenData['expires_at'] ?? 0))) {
            @unlink($tokenPath);
            return redirect()->route('database.index')->with('error', 'Database session token has expired.');
        }

        $database = $tokenData['database'];

        if (!$this->checkDatabaseAccess($database)) {
            return redirect()->route('database.index')->with('error', 'Permission denied for this database.');
        }

        return \Inertia\Inertia::render('Database/ManagerPage', [
            'database' => $database,
            'token' => $token
        ]);
    }

    /**
     * Sanitize SQL identifier (table name or column name)
     */
    private function sanitizeIdentifier(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        if (empty($clean)) {
            throw new \InvalidArgumentException("Invalid SQL identifier");
        }
        return $clean;
    }

    /**
     * 1. Get list of all tables in a database
     */
    public function getTables(string $db)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied: You do not have access to this database.'], 403);
            }

            $pdo = $this->getPdoConnection($db);
            $stmt = $pdo->query("SHOW TABLE STATUS FROM `" . $this->sanitizeIdentifier($db) . "`");
            $tablesStatus = $stmt->fetchAll();

            $result = [];
            foreach ($tablesStatus as $row) {
                $name = $row['Name'] ?? $row['name'] ?? '';
                if (empty($name)) continue;

                $dataLength = (int)($row['Data_length'] ?? 0);
                $indexLength = (int)($row['Index_length'] ?? 0);
                $totalBytes = $dataLength + $indexLength;

                $result[] = [
                    'name' => $name,
                    'engine' => $row['Engine'] ?? 'InnoDB',
                    'rows' => (int)($row['Rows'] ?? 0),
                    'data_length' => $dataLength,
                    'index_length' => $indexLength,
                    'total_size' => $this->formatBytes($totalBytes),
                    'total_bytes' => $totalBytes,
                    'collation' => $row['Collation'] ?? 'utf8mb4_unicode_ci',
                    'auto_increment' => $row['Auto_increment'] ?? null,
                    'comment' => $row['Comment'] ?? '',
                    'created_at' => $row['Create_time'] ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'database' => $db,
                'tables' => $result,
                'count' => count($result)
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fetch tables for DB {$db}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 2. Get full table schema (columns, indexes, foreign keys)
     */
    public function getTableSchema(string $db, string $table)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($table);

            // Columns
            $stmt = $pdo->query("SHOW FULL COLUMNS FROM `{$safeDb}`.`{$safeTable}`");
            $rawCols = $stmt->fetchAll();

            $columns = [];
            $primaryKeys = [];

            foreach ($rawCols as $col) {
                $isPrimary = ($col['Key'] === 'PRI');
                if ($isPrimary) {
                    $primaryKeys[] = $col['Field'];
                }

                $columns[] = [
                    'name' => $col['Field'],
                    'type' => $col['Type'],
                    'null' => $col['Null'] === 'YES',
                    'key' => $col['Key'],
                    'default' => $col['Default'],
                    'extra' => $col['Extra'],
                    'collation' => $col['Collation'] ?? null,
                    'comment' => $col['Comment'] ?? '',
                    'is_primary' => $isPrimary,
                    'is_auto_increment' => str_contains(strtolower($col['Extra']), 'auto_increment'),
                ];
            }

            // Indexes
            $stmtIndex = $pdo->query("SHOW INDEX FROM `{$safeDb}`.`{$safeTable}`");
            $rawIndexes = $stmtIndex->fetchAll();

            $groupedIndexes = [];
            foreach ($rawIndexes as $idx) {
                $keyName = $idx['Key_name'];
                if (!isset($groupedIndexes[$keyName])) {
                    $groupedIndexes[$keyName] = [
                        'name' => $keyName,
                        'unique' => $idx['Non_unique'] == 0,
                        'type' => $idx['Index_type'] ?? 'BTREE',
                        'columns' => []
                    ];
                }
                $groupedIndexes[$keyName]['columns'][] = $idx['Column_name'];
            }

            // Foreign Keys
            $fkSql = "SELECT 
                        CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                      FROM information_schema.KEY_COLUMN_USAGE
                      WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND REFERENCED_TABLE_NAME IS NOT NULL";
            $fkStmt = $pdo->prepare($fkSql);
            $fkStmt->execute(['db' => $db, 'tbl' => $table]);
            $foreignKeys = $fkStmt->fetchAll();

            return response()->json([
                'success' => true,
                'database' => $db,
                'table' => $table,
                'columns' => $columns,
                'primary_keys' => $primaryKeys,
                'indexes' => array_values($groupedIndexes),
                'foreign_keys' => $foreignKeys
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fetch schema for {$db}.{$table}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. Get paginated table rows with search & sorting (DML - Read)
     */
    public function getTableData(Request $request, string $db, string $table)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($table);

            $page = max(1, (int)$request->input('page', 1));
            $perPage = max(5, min(500, (int)$request->input('per_page', 25)));
            $offset = ($page - 1) * $perPage;

            $sortCol = $request->input('sort_col');
            $sortDir = strtoupper($request->input('sort_dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $searchQuery = trim($request->input('search_query', ''));
            $searchCol = $request->input('search_col', '');

            // Columns and primary key
            $colsStmt = $pdo->query("SHOW FULL COLUMNS FROM `{$safeDb}`.`{$safeTable}`");
            $rawCols = $colsStmt->fetchAll();
            $columnNames = array_column($rawCols, 'Field');
            $primaryKeys = array_values(array_filter(array_map(function($c) {
                return $c['Key'] === 'PRI' ? $c['Field'] : null;
            }, $rawCols)));

            // Where clause logic
            $whereClause = "";
            $bindings = [];

            if (!empty($searchQuery)) {
                if (!empty($searchCol) && in_array($searchCol, $columnNames)) {
                    $whereClause = " WHERE `" . $this->sanitizeIdentifier($searchCol) . "` LIKE :search ";
                    $bindings[':search'] = "%{$searchQuery}%";
                } else {
                    $orConditions = [];
                    foreach ($columnNames as $idx => $cName) {
                        $param = ":search_{$idx}";
                        $orConditions[] = "`" . $this->sanitizeIdentifier($cName) . "` LIKE {$param}";
                        $bindings[$param] = "%{$searchQuery}%";
                    }
                    if (!empty($orConditions)) {
                        $whereClause = " WHERE (" . implode(" OR ", $orConditions) . ")";
                    }
                }
            }

            // Total count
            $countSql = "SELECT COUNT(*) as total FROM `{$safeDb}`.`{$safeTable}`{$whereClause}";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($bindings);
            $totalRows = (int)$countStmt->fetchColumn();

            // Order by clause
            $orderByClause = "";
            if (!empty($sortCol) && in_array($sortCol, $columnNames)) {
                $safeSort = $this->sanitizeIdentifier($sortCol);
                $orderByClause = " ORDER BY `{$safeSort}` {$sortDir}";
            } elseif (!empty($primaryKeys)) {
                $safePk = $this->sanitizeIdentifier($primaryKeys[0]);
                $orderByClause = " ORDER BY `{$safePk}` ASC";
            }

            // Data query
            $dataSql = "SELECT * FROM `{$safeDb}`.`{$safeTable}`{$whereClause}{$orderByClause} LIMIT {$perPage} OFFSET {$offset}";
            $dataStmt = $pdo->prepare($dataSql);
            $dataStmt->execute($bindings);
            $rows = $dataStmt->fetchAll();

            return response()->json([
                'success' => true,
                'columns' => $columnNames,
                'primary_keys' => $primaryKeys,
                'rows' => $rows,
                'total_rows' => $totalRows,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalRows / $perPage)
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fetch table data for {$db}.{$table}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 4. Insert row into table
     */
    public function insertRow(Request $request, string $db, string $table)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $data = $request->input('data', []);
            if (empty($data) || !is_array($data)) {
                return response()->json(['error' => 'No row data provided'], 400);
            }

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($table);

            $colsStmt = $pdo->query("SHOW FULL COLUMNS FROM `{$safeDb}`.`{$safeTable}`");
            $validCols = array_column($colsStmt->fetchAll(), 'Field');

            $fields = [];
            $placeholders = [];
            $bindings = [];

            foreach ($data as $key => $val) {
                if (in_array($key, $validCols)) {
                    $safeKey = $this->sanitizeIdentifier($key);
                    $param = ":in_{$safeKey}";
                    $fields[] = "`{$safeKey}`";
                    $placeholders[] = $param;
                    $bindings[$param] = $val;
                }
            }

            if (empty($fields)) {
                return response()->json(['error' => 'No valid column fields matched'], 400);
            }

            $sql = "INSERT INTO `{$safeDb}`.`{$safeTable}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);

            $lastId = $pdo->lastInsertId();

            return response()->json([
                'success' => true,
                'message' => 'Row inserted successfully',
                'insert_id' => $lastId ?: null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 5. Update row in table
     */
    public function updateRow(Request $request, string $db, string $table)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $where = $request->input('where', []);
            $data = $request->input('data', []);

            if (empty($where) || empty($data)) {
                return response()->json(['error' => 'Missing update criteria or data'], 400);
            }

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($table);

            $colsStmt = $pdo->query("SHOW FULL COLUMNS FROM `{$safeDb}`.`{$safeTable}`");
            $validCols = array_column($colsStmt->fetchAll(), 'Field');

            $setClauses = [];
            $whereClauses = [];
            $bindings = [];

            foreach ($data as $key => $val) {
                if (in_array($key, $validCols)) {
                    $safeKey = $this->sanitizeIdentifier($key);
                    $param = ":set_{$safeKey}";
                    $setClauses[] = "`{$safeKey}` = {$param}";
                    $bindings[$param] = $val;
                }
            }

            foreach ($where as $key => $val) {
                if (in_array($key, $validCols)) {
                    $safeKey = $this->sanitizeIdentifier($key);
                    $param = ":where_{$safeKey}";
                    $whereClauses[] = "`{$safeKey}` = {$param}";
                    $bindings[$param] = $val;
                }
            }

            if (empty($setClauses) || empty($whereClauses)) {
                return response()->json(['error' => 'Invalid columns in update request'], 400);
            }

            $sql = "UPDATE `{$safeDb}`.`{$safeTable}` SET " . implode(', ', $setClauses) . " WHERE " . implode(' AND ', $whereClauses);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);

            return response()->json([
                'success' => true,
                'affected_rows' => $stmt->rowCount(),
                'message' => 'Row updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 6. Delete row(s) from table
     */
    public function deleteRow(Request $request, string $db, string $table)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $rows = $request->input('rows', []); // Array of key-value dictionaries
            if (empty($rows)) {
                $where = $request->input('where', []);
                if (!empty($where)) $rows = [$where];
            }

            if (empty($rows)) {
                return response()->json(['error' => 'No rows specified for deletion'], 400);
            }

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($table);

            $colsStmt = $pdo->query("SHOW FULL COLUMNS FROM `{$safeDb}`.`{$safeTable}`");
            $validCols = array_column($colsStmt->fetchAll(), 'Field');

            $deletedCount = 0;

            foreach ($rows as $rowIndex => $whereDict) {
                $whereClauses = [];
                $bindings = [];
                foreach ($whereDict as $key => $val) {
                    if (in_array($key, $validCols)) {
                        $safeKey = $this->sanitizeIdentifier($key);
                        $param = ":del_{$rowIndex}_{$safeKey}";
                        $whereClauses[] = "`{$safeKey}` = {$param}";
                        $bindings[$param] = $val;
                    }
                }

                if (!empty($whereClauses)) {
                    $sql = "DELETE FROM `{$safeDb}`.`{$safeTable}` WHERE " . implode(' AND ', $whereClauses);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($bindings);
                    $deletedCount += $stmt->rowCount();
                }
            }

            return response()->json([
                'success' => true,
                'deleted_rows' => $deletedCount,
                'message' => "Successfully deleted {$deletedCount} row(s)"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 7. Create a new table (DDL)
     */
    public function createTable(Request $request, string $db)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $request->validate([
                'name' => 'required|string|max:64|regex:/^[a-zA-Z0-9_]+$/',
                'columns' => 'required|array|min:1',
                'engine' => 'nullable|string',
                'collation' => 'nullable|string'
            ]);

            $tableName = $request->input('name');
            $columns = $request->input('columns');
            $engine = $request->input('engine', 'InnoDB');
            $collation = $request->input('collation', 'utf8mb4_unicode_ci');

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($tableName);

            $colDefs = [];
            $primaryKeys = [];

            foreach ($columns as $col) {
                $colName = $this->sanitizeIdentifier($col['name']);
                $colType = strtoupper(trim($col['type'] ?? 'VARCHAR'));
                $colLength = !empty($col['length']) ? "({$col['length']})" : "";
                $nullable = !empty($col['nullable']) ? "NULL" : "NOT NULL";
                $autoInc = !empty($col['auto_increment']) ? "AUTO_INCREMENT" : "";

                $default = "";
                if (array_key_exists('default', $col) && $col['default'] !== null && $col['default'] !== '') {
                    $defVal = $col['default'];
                    if (in_array(strtoupper($defVal), ['CURRENT_TIMESTAMP', 'NULL'])) {
                        $default = "DEFAULT {$defVal}";
                    } else {
                        $default = "DEFAULT " . $pdo->quote($defVal);
                    }
                }

                $comment = !empty($col['comment']) ? "COMMENT " . $pdo->quote($col['comment']) : "";

                $colDefs[] = "`{$colName}` {$colType}{$colLength} {$nullable} {$autoInc} {$default} {$comment}";

                if (!empty($col['primary_key'])) {
                    $primaryKeys[] = "`{$colName}`";
                }
            }

            if (!empty($primaryKeys)) {
                $colDefs[] = "PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
            }

            $sql = "CREATE TABLE `{$safeDb}`.`{$safeTable}` (\n  " . implode(",\n  ", $colDefs) . "\n) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE={$collation}";
            $pdo->exec($sql);

            return response()->json([
                'success' => true,
                'message' => "Table '{$tableName}' created successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 8. Drop table (DDL)
     */
    public function dropTable(Request $request, string $db, string $table)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($table);

            $pdo->exec("DROP TABLE IF EXISTS `{$safeDb}`.`{$safeTable}`");

            return response()->json([
                'success' => true,
                'message' => "Table '{$table}' dropped successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 9. Truncate table (DDL)
     */
    public function truncateTable(Request $request, string $db, string $table)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $pdo = $this->getPdoConnection($db);
            $safeDb = $this->sanitizeIdentifier($db);
            $safeTable = $this->sanitizeIdentifier($table);

            $pdo->exec("TRUNCATE TABLE `{$safeDb}`.`{$safeTable}`");

            return response()->json([
                'success' => true,
                'message' => "Table '{$table}' truncated successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 10. Execute raw SQL query (SQL Console)
     */
    public function executeQuery(Request $request, string $db)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $sql = trim($request->input('sql', ''));
            if (empty($sql)) {
                return response()->json(['error' => 'SQL query cannot be empty'], 400);
            }

            $pdo = $this->getPdoConnection($db);
            $startTime = microtime(true);

            $firstWord = strtoupper(strtok($sql, " \n\t;"));
            $isSelectLike = in_array($firstWord, ['SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'CHECK']);

            if ($isSelectLike) {
                $stmt = $pdo->query($sql);
                $rows = $stmt->fetchAll();
                $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

                $columns = [];
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                } else {
                    for ($i = 0; $i < $stmt->columnCount(); $i++) {
                        $meta = $stmt->getColumnMeta($i);
                        if (isset($meta['name'])) {
                            $columns[] = $meta['name'];
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'type' => 'select',
                    'columns' => $columns,
                    'rows' => $rows,
                    'count' => count($rows),
                    'execution_time_ms' => $executionTimeMs
                ]);
            } else {
                $affected = $pdo->exec($sql);
                $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

                return response()->json([
                    'success' => true,
                    'type' => 'affected',
                    'affected_rows' => $affected !== false ? $affected : 0,
                    'message' => "Query executed successfully. Affected rows: " . ($affected !== false ? $affected : 0),
                    'execution_time_ms' => $executionTimeMs
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 11. Export database or tables (SQL / CSV)
     */
    public function exportDatabase(Request $request, string $db)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $format = strtolower($request->input('format', 'sql'));
            $table = $request->input('table');
            $safeDb = $this->sanitizeIdentifier($db);

            if ($format === 'csv' && !empty($table)) {
                $safeTable = $this->sanitizeIdentifier($table);
                $pdo = $this->getPdoConnection($db);
                $stmt = $pdo->query("SELECT * FROM `{$safeDb}`.`{$safeTable}`");
                $rows = $stmt->fetchAll();

                $output = fopen('php://temp', 'w+');
                if (!empty($rows)) {
                    fputcsv($output, array_keys($rows[0]));
                    foreach ($rows as $r) {
                        fputcsv($output, $r);
                    }
                }
                rewind($output);
                $csvData = stream_get_contents($output);
                fclose($output);

                return response($csvData)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', "attachment; filename=\"{$safeTable}.csv\"");
            } else {
                // SQL Dump via mysqldump command
                $host = config('database.connections.mysql.host', '127.0.0.1');
                $user = config('database.connections.mysql.username', 'root');
                $pass = config('database.connections.mysql.password', '');

                $passArg = !empty($pass) ? "-p" . escapeshellarg($pass) : "";
                $tableArg = !empty($table) ? escapeshellarg($this->sanitizeIdentifier($table)) : "";

                $cmd = "mysqldump -h " . escapeshellarg($host) . " -u " . escapeshellarg($user) . " {$passArg} " . escapeshellarg($safeDb) . " {$tableArg} 2>/dev/null";
                exec($cmd, $dumpOutput, $code);

                if ($code !== 0 || empty($dumpOutput)) {
                    // Fallback to PHP query generation if mysqldump is missing/restricted
                    $dumpOutput = ["-- Nimbus DB Dump for {$safeDb}", "SET FOREIGN_KEY_CHECKS=0;"];
                    $pdo = $this->getPdoConnection($db);
                    $tablesStmt = $pdo->query("SHOW TABLES FROM `{$safeDb}`");
                    $tbls = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($tbls as $t) {
                        if (!empty($table) && $t !== $table) continue;
                        $createStmt = $pdo->query("SHOW CREATE TABLE `{$safeDb}`.`{$t}`")->fetch();
                        $dumpOutput[] = "\n-- Table structure for `{$t}`\nDROP TABLE IF EXISTS `{$t}`;";
                        $dumpOutput[] = ($createStmt['Create Table'] ?? $createStmt['create table'] ?? '') . ";";
                    }
                }

                $sqlContent = is_array($dumpOutput) ? implode("\n", $dumpOutput) : $dumpOutput;
                $filename = !empty($table) ? "{$safeTable}.sql" : "{$safeDb}_dump.sql";

                return response($sqlContent)
                    ->header('Content-Type', 'application/sql')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 12. Import SQL dump into database
     */
    public function importDatabase(Request $request, string $db)
    {
        try {
            if (!$this->checkDatabaseAccess($db)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            if (!$request->hasFile('file')) {
                return response()->json(['error' => 'No dump file uploaded'], 400);
            }

            $file = $request->file('file');
            $sqlContent = file_get_contents($file->getRealPath());

            if (empty(trim($sqlContent))) {
                return response()->json(['error' => 'Uploaded file is empty'], 400);
            }

            $pdo = $this->getPdoConnection($db);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->exec($sqlContent);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            return response()->json([
                'success' => true,
                'message' => "SQL dump imported successfully into database '{$db}'"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to format bytes
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
