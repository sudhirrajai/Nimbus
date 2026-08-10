<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'linux_user',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function websites()
    {
        return $this->hasMany(UserWebsite::class);
    }

    // ─── Role Helpers ────────────────────────────────────────────

    public function isRoot(): bool
    {
        return $this->role === 'root';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isRootOrAdmin(): bool
    {
        return in_array($this->role, ['root', 'admin']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ─── Domain Access ───────────────────────────────────────────

    /**
     * Check if user can access a specific domain
     */
    public function canAccessDomain(string $domain): bool
    {
        if ($this->isRoot()) return true;

        $targetDomain = strtolower(trim($domain));
        return $this->websites()->get()->contains(function ($w) use ($targetDomain) {
            return strtolower(trim($w->domain)) === $targetDomain;
        });
    }

    /**
     * Check if user has a specific permission for a domain
     */
    public function hasDomainPermission(string $domain, string $permission): bool
    {
        if ($this->isRoot()) return true;

        $targetDomain = strtolower(trim($domain));
        $website = $this->websites()->get()->first(function ($w) use ($targetDomain) {
            return strtolower(trim($w->domain)) === $targetDomain;
        });

        if (!$website) return false;

        return $website->hasPermission($permission);
    }

    /**
     * Get all domains this user can access
     */
    public function accessibleDomains(): array
    {
        if ($this->isRoot()) {
            // Root sees all domains in /var/www/
            $domains = [];
            $dirs = glob('/var/www/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $name = basename($dir);
                if ($name !== 'html') {
                    $domains[] = $name;
                }
            }
            return $domains;
        }

        return array_values(array_unique(array_filter(array_map(function ($d) {
            return strtolower(trim($d));
        }, $this->websites()->pluck('domain')->toArray()))));
    }

    /**
     * Get all databases this user can access
     */
    public function accessibleDatabases(): array
    {
        if ($this->isRoot()) {
            try {
                $output = [];
                exec("sudo mysql -N -e 'SHOW DATABASES' 2>/dev/null", $output);
                $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin', 'nimbus', 'roundcube'];
                $dbs = [];
                foreach ($output as $line) {
                    $name = trim($line);
                    if ($name && !in_array($name, $systemDbs)) {
                        $dbs[] = $name;
                    }
                }
                return array_unique(array_map('strtolower', $dbs));
            } catch (\Exception $e) {
                // Fallback to scan
            }
        }

        $databases = [];
        
        // 1. Databases created by this user
        try {
            $createdDbs = \App\Models\NimbusDatabase::where('created_by', $this->email)->pluck('name')->toArray();
            $databases = array_merge($databases, $createdDbs);
        } catch (\Exception $e) {
            \Log::warning("Failed to fetch created databases for user {$this->email}: " . $e->getMessage());
        }

        // 2. Databases manually linked to user's accessible domains
        $domains = array_map('strtolower', $this->accessibleDomains());
        try {
            $linkedDbs = \App\Models\NimbusDatabase::whereNotNull('domain')
                ->get()
                ->filter(function ($db) use ($domains) {
                    return in_array(strtolower(trim($db->domain)), $domains);
                })
                ->pluck('name')
                ->toArray();
            $databases = array_merge($databases, $linkedDbs);
        } catch (\Exception $e) {
            \Log::warning("Failed to fetch linked databases for user {$this->email}: " . $e->getMessage());
        }

        // 3. Databases associated with accessible domains via configuration files
        foreach ($domains as $domain) {
            $path = "/var/www/{$domain}";
            if (!is_dir($path)) continue;

            $pathsToCheck = [$path];
            
            // Check first-level subdirectories (1 level deep) for configs
            $subDirs = glob($path . '/*', GLOB_ONLYDIR);
            if (is_array($subDirs)) {
                foreach ($subDirs as $subDir) {
                    $subDirName = basename($subDir);
                    if (in_array(strtolower($subDirName), ['node_modules', '.git', 'vendor', 'storage', 'tests', 'public', 'assets', 'dist', 'build'])) {
                        continue;
                    }
                    $pathsToCheck[] = $subDir;
                }
            }

            foreach ($pathsToCheck as $checkPath) {
                // Check .env
                $envPath = "{$checkPath}/.env";
                if (file_exists($envPath)) {
                    $content = file_get_contents($envPath);
                    if (preg_match('/^\s*DB_DATABASE\s*=\s*(.+)$/m', $content, $matches)) {
                        $db = trim($matches[1], "\"' \r\n");
                        if (!empty($db)) {
                            $databases[] = $db;
                        }
                    }
                }

                // Check wp-config.php
                $wpPath = "{$checkPath}/wp-config.php";
                if (file_exists($wpPath)) {
                    $content = file_get_contents($wpPath);
                    if (preg_match('/define\(\s*[\'"]DB_NAME[\'"]\s*,\s*[\'"](.+)[\'"]\s*\)/', $content, $matches)) {
                        $db = trim($matches[1]);
                        if (!empty($db)) {
                            $databases[] = $db;
                        }
                    }
                }

                // Check Core PHP config files
                $corePhpFiles = [
                    "{$checkPath}/config.php",
                    "{$checkPath}/config/config.php",
                    "{$checkPath}/config/database.php",
                    "{$checkPath}/inc/config.php",
                    "{$checkPath}/includes/config.php",
                    "{$checkPath}/db.php",
                    "{$checkPath}/configuration.php"
                ];

                foreach ($corePhpFiles as $phpFile) {
                    if (file_exists($phpFile)) {
                        $content = file_get_contents($phpFile);
                        if (preg_match('/define\(\s*[\'"](?:DB_NAME|DB_DATABASE|DB_DB|DATABASE_NAME)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i', $content, $matches)) {
                            $db = trim($matches[1]);
                            if (!empty($db)) $databases[] = $db;
                        }
                        if (preg_match('/\$(?:db_name|dbname|database|db_database|db)\s*=\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
                            $db = trim($matches[1]);
                            if (!empty($db)) $databases[] = $db;
                        }
                        if (preg_match('/[\'"](?:database|dbname|db_name)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
                            $db = trim($matches[1]);
                            if (!empty($db)) $databases[] = $db;
                        }
                    }
                }
            }
        }

        return array_unique(array_map('strtolower', $databases));
    }

    /**
     * Check if user can access a database
     */
    public function canAccessDatabase(string $dbName): bool
    {
        if ($this->isRoot()) return true;

        return in_array(strtolower($dbName), $this->accessibleDatabases());
    }
}
