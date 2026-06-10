<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmailController extends Controller
{
    /**
     * Email management page
     */
    public function index()
    {
        return Inertia::render('Email/Index');
    }

    /**
     * Get mail server status
     */
    public function getStatus()
    {
        try {
            // Check if Postfix is installed
            $postfixInstalled = file_exists('/etc/postfix/main.cf');
            $dovecotInstalled = file_exists('/etc/dovecot/dovecot.conf');
            
            // Check if Roundcube is configured by Nimbus (must contain our custom signature and modern smtp_host)
            $roundcubeInstalled = false;
            foreach (['/etc/roundcube/config.inc.php', '/var/lib/roundcube/config/config.inc.php'] as $file) {
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    if (strpos($content, 'Nimbus Webmail') !== false && strpos($content, 'smtp_host') !== false) {
                        $roundcubeInstalled = true;
                        break;
                    }
                }
            }

            // Auto-configure Roundcube if mail server is installed but Roundcube config is missing signature
            if ($postfixInstalled && $dovecotInstalled && !$roundcubeInstalled) {
                try {
                    $response = $this->configureRoundcube();
                    $data = json_decode($response->getContent(), true);
                    if (isset($data['success']) && $data['success']) {
                        $roundcubeInstalled = true;
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to auto-configure Roundcube in getStatus: " . $e->getMessage());
                }
            }

            // Check if services are running
            $postfixRunning = false;
            $dovecotRunning = false;

            if ($postfixInstalled) {
                exec('systemctl is-active postfix 2>/dev/null', $output, $code);
                $postfixRunning = ($code === 0);
            }

            if ($dovecotInstalled) {
                exec('systemctl is-active dovecot 2>/dev/null', $output2, $code2);
                $dovecotRunning = ($code2 === 0);
            }

            // Get stats
            $domainCount = DB::table('virtual_domains')->where('active', true)->count();
            $accountCount = DB::table('virtual_users')->where('active', true)->count();
            $aliasCount = DB::table('virtual_aliases')->where('active', true)->count();

            return response()->json([
                'installed' => $postfixInstalled && $dovecotInstalled,
                'postfix' => [
                    'installed' => $postfixInstalled,
                    'running' => $postfixRunning
                ],
                'dovecot' => [
                    'installed' => $dovecotInstalled,
                    'running' => $dovecotRunning
                ],
                'roundcube' => [
                    'installed' => $roundcubeInstalled
                ],
                'stats' => [
                    'domains' => $domainCount,
                    'accounts' => $accountCount,
                    'aliases' => $aliasCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'installed' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Install mail server (Postfix, Dovecot, Roundcube)
     * Runs in background with log file for real-time output
     */
    public function installMailServer(Request $request)
    {
        try {
            $request->validate([
                'hostname' => 'required|string|regex:/^[a-zA-Z0-9.-]+$/|max:255'
            ]);
            
            $hostname = $request->input('hostname', gethostname());
            
            // Check if another installation is in progress
            $lockFile = storage_path('logs/nimbus_install.lock');
            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json([
                    'error' => "Another installation is in progress: {$lockContent}. Please wait for it to complete."
                ], 409);
            }
            
            // Create lock file
            file_put_contents($lockFile, 'Mail Server installation');
            
            // Get MySQL credentials
            $dbHost = config('database.connections.mysql.host');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            // Log file path
            $logFile = storage_path('logs/mailserver_install.log');
            $statusFile = storage_path('logs/mailserver_install.status');
            
            // Clear previous logs
            file_put_contents($logFile, "=== Mail Server Installation Started ===\n");
            file_put_contents($logFile, "Hostname: {$hostname}\n", FILE_APPEND);
            file_put_contents($logFile, "Time: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);
            file_put_contents($statusFile, 'running');

            // Detect PHP version for roundcube extensions
            $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

            // Installation script
            $script = <<<BASH
#!/bin/bash

# Disable interactive prompts (Ubuntu 22.04+ needrestart, kernel upgrade dialogs)
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1

# Prevent any services from restarting automatically during apt-get
echo -e '#!/bin/sh\nexit 101' | sudo tee /usr/sbin/policy-rc.d > /dev/null
sudo chmod +x /usr/sbin/policy-rc.d

# Disable needrestart temporarily for this installation
if [ -f /etc/needrestart/needrestart.conf ]; then
    sudo sed -i "s/^#\\\$nrconf{restart} = 'i';/\\\$nrconf{restart} = 'a';/" /etc/needrestart/needrestart.conf 2>/dev/null || true
fi

# Function to wait for apt locks
wait_for_apt() {
    while sudo fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 || sudo fuser /var/lib/apt/lists/lock >/dev/null 2>&1; do
        echo "Waiting for other apt process to finish..."
        sleep 2
    done
}

# Pre-emptively stop Apache2 if it is installed, to free up port 80
if [ -f /usr/sbin/apache2 ]; then
    echo "Stopping Apache2 pre-emptively to avoid Nginx port conflicts..."
    sudo systemctl stop apache2 2>/dev/null || true
    sudo systemctl disable apache2 2>/dev/null || true
fi

# Clean up any previously broken package installs
echo "Fixing any existing broken package installations..."
wait_for_apt
sudo DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 dpkg --configure -a 2>&1
sudo DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 apt-get install -f -y 2>&1

echo "[1/8] Updating package list..."
wait_for_apt
sudo DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 apt-get update 2>&1

echo ""
echo "[2/8] Installing Postfix..."
wait_for_apt
echo "postfix postfix/mailname string {$hostname}" | sudo debconf-set-selections
echo "postfix postfix/main_mailer_type string 'Internet Site'" | sudo debconf-set-selections
sudo DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 apt-get install -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" postfix postfix-mysql 2>&1

echo ""
echo "[3/8] Installing Dovecot..."
wait_for_apt
sudo DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 apt-get install -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql 2>&1

echo ""
echo "[4/8] Installing Roundcube (without Apache)..."
wait_for_apt

# PHP version detected by Nimbus: {$phpVersion}
echo "Using PHP version: {$phpVersion}"

# Preseed roundcube to skip interactive prompts
echo "roundcube-core roundcube/dbconfig-install boolean false" | sudo debconf-set-selections
echo "roundcube-core roundcube/reconfigure-webserver multiselect none" | sudo debconf-set-selections

# Pin the current PHP version to prevent installing new PHP versions
# Install only roundcube core without recommends to avoid pulling php dependencies
sudo env DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 apt-get install -y --no-install-recommends roundcube-core roundcube-mysql 2>&1

# Install roundcube PHP package for current version only (using noninteractive mode)
sudo env DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a NEEDRESTART_SUSPEND=1 apt-get install -y --no-install-recommends php{$phpVersion}-intl php{$phpVersion}-zip php{$phpVersion}-ldap 2>&1 || true

# ====== PHP 8.5 CLEANUP ======
# If PHP 8.5 got installed by accident, remove it and stick with intended version
if dpkg -l | grep -q "php8.5"; then
    echo ""
    echo "WARNING: PHP 8.5 was installed. Removing it to keep PHP {$phpVersion}..."
    sudo apt-get remove -y --purge php8.5* 2>&1 || true
    sudo apt-get autoremove -y 2>&1 || true
fi

# Make sure we're still using the correct PHP version
sudo update-alternatives --set php /usr/bin/php{$phpVersion} 2>&1 || true

# Schedule PHP-FPM restart for 1 minute after the script finishes to avoid killing this script
sudo bash -c "echo 'sleep 5 && systemctl restart php{$phpVersion}-fpm' | at now" 2>/dev/null || \
sudo systemd-run --on-active=10 sudo systemctl restart php{$phpVersion}-fpm 2>&1 || true

# Stop and disable Apache2 if it was installed as a dependency
if systemctl is-active --quiet apache2 2>/dev/null; then
    echo "Stopping Apache2 (we use Nginx instead)..."
    sudo systemctl stop apache2
    sudo systemctl disable apache2
fi

echo ""
echo "[5/8] Creating mail directories and linking webmail..."
sudo mkdir -p /var/mail/vhosts
sudo groupadd -g 5000 vmail 2>/dev/null || echo "Group vmail already exists"
sudo useradd -g vmail -u 5000 vmail -d /var/mail 2>/dev/null || echo "User vmail already exists"
sudo chown -R vmail:vmail /var/mail

# Link Roundcube to Nimbus public directory so it can be served by Nginx
if [ ! -L /usr/local/nimbus/public/roundcube ]; then
    sudo ln -sf /var/lib/roundcube/public_html /usr/local/nimbus/public/roundcube
fi

echo "Mail directories created and webmail linked"

echo ""
echo "[6/8] Configuring Postfix..."
sudo tee /etc/postfix/mysql-virtual-mailbox-domains.cf > /dev/null << 'EOF'
user = {$dbUser}
password = {$dbPass}
hosts = {$dbHost}
dbname = {$dbName}
query = SELECT 1 FROM virtual_domains WHERE name='%s' AND active=1
EOF

sudo tee /etc/postfix/mysql-virtual-mailbox-maps.cf > /dev/null << 'EOF'
user = {$dbUser}
password = {$dbPass}
hosts = {$dbHost}
dbname = {$dbName}
query = SELECT maildir FROM virtual_users WHERE email='%s' AND active=1
EOF

sudo tee /etc/postfix/mysql-virtual-alias-maps.cf > /dev/null << 'EOF'
user = {$dbUser}
password = {$dbPass}
hosts = {$dbHost}
dbname = {$dbName}
query = SELECT destination FROM virtual_aliases WHERE source='%s' AND active=1
EOF

sudo postconf -e "myhostname = {$hostname}"
sudo postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"
sudo postconf -e "virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf"
sudo postconf -e "virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf"
sudo postconf -e "virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf"
echo "Postfix configured"

echo ""
echo "[7/8] Configuring Dovecot..."
sudo tee /etc/dovecot/conf.d/10-mail.conf > /dev/null << 'EOF'
mail_location = maildir:/var/mail/vhosts/%d/%n
mail_privileged_group = mail

namespace inbox {
  inbox = yes
  location = 
  mailbox Drafts {
    special_use = \Drafts
  }
  mailbox Junk {
    special_use = \Junk
  }
  mailbox Sent {
    special_use = \Sent
    auto = subscribe
  }
  mailbox "Sent Messages" {
    special_use = \Sent
  }
  mailbox Trash {
    special_use = \Trash
    auto = subscribe
  }
  prefix = 
}
EOF

sudo tee /etc/dovecot/conf.d/10-auth.conf > /dev/null << 'EOF'
disable_plaintext_auth = yes
auth_mechanisms = plain login
!include auth-sql.conf.ext
EOF

sudo tee /etc/dovecot/conf.d/auth-sql.conf.ext > /dev/null << 'EOF'
passdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}
userdb {
  driver = static
  args = uid=vmail gid=vmail home=/var/mail/vhosts/%d/%n
}
EOF

sudo tee /etc/dovecot/dovecot-sql.conf.ext > /dev/null << 'EOF'
driver = mysql
connect = host={$dbHost} dbname={$dbName} user={$dbUser} password={$dbPass}
default_pass_scheme = SHA512-CRYPT
password_query = SELECT email as user, password FROM virtual_users WHERE email='%u' AND active=1
EOF

sudo tee /etc/dovecot/conf.d/10-master.conf > /dev/null << 'EOF'
service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}

service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0666
    user = postfix
    group = postfix
  }
  unix_listener auth-userdb {
    mode = 0600
    user = vmail
  }
  user = dovecot
}

service auth-worker {
  user = vmail
}
EOF

sudo chmod 640 /etc/dovecot/dovecot-sql.conf.ext
sudo chown root:dovecot /etc/dovecot/dovecot-sql.conf.ext
echo "Dovecot configured"

echo ""
echo "[8/8] Starting services..."
sudo rm -f /usr/sbin/policy-rc.d

# Configure firewall rules for Mail Server
echo "Configuring firewall rules for Mail Server..."
if command -v ufw >/dev/null 2>&1; then
    sudo ufw allow 25/tcp 2>/dev/null || true
    sudo ufw allow 143/tcp 2>/dev/null || true
    sudo ufw allow 993/tcp 2>/dev/null || true
    sudo ufw allow 110/tcp 2>/dev/null || true
    sudo ufw allow 995/tcp 2>/dev/null || true
    sudo ufw allow 587/tcp 2>/dev/null || true
    sudo ufw allow 465/tcp 2>/dev/null || true
    sudo ufw reload 2>/dev/null || true
    echo "Firewall ports 25, 143, 993, 110, 995, 587, and 465 auto-enabled."
else
    echo "ufw firewall not found, skipping port configuration."
fi

sudo systemctl restart postfix
sudo systemctl restart dovecot
sudo systemctl enable postfix
sudo systemctl enable dovecot
echo "Services started"

echo ""
echo "=========================================="
echo "  Mail Server Installation Complete!"
echo "=========================================="
echo ""
echo "Postfix: $(systemctl is-active postfix)"
echo "Dovecot: $(systemctl is-active dovecot)"

# Remove lock file
rm -f /usr/local/nimbus/storage/logs/nimbus_install.lock
BASH;

            // Write script to temp file
            $scriptPath = '/tmp/install_mailserver.sh';
            file_put_contents($scriptPath, $script);
            chmod($scriptPath, 0755);

            // Execute script completely detached using systemd-run to prevent PHP-FPM deadlocks
            $command = "sudo bash {$scriptPath} >> {$logFile} 2>&1; echo \$? > {$statusFile}; rm -f {$scriptPath}";
            exec("sudo systemd-run --unit=nimbus-mail-install-$(date +%s) bash -c '{$command}' > /dev/null 2>&1 &");

            return response()->json([
                'success' => true,
                'message' => 'Installation started',
                'logFile' => 'mailserver_install.log'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Uninstall mail server (Postfix, Dovecot, Roundcube)
     * Runs in background with log file for real-time output
     */
    public function uninstallMailServer(Request $request)
    {
        try {
            // Check if another installation/uninstallation is in progress
            $lockFile = storage_path('logs/nimbus_install.lock');
            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json([
                    'error' => "Another operation is in progress: {$lockContent}. Please wait for it to complete."
                ], 409);
            }
            
            // Create lock file
            file_put_contents($lockFile, 'Mail Server uninstallation');
            
            // Log file paths
            $logFile = storage_path('logs/mailserver_install.log');
            $statusFile = storage_path('logs/mailserver_install.status');
            
            // Clear previous logs
            file_put_contents($logFile, "=== Mail Server Uninstallation Started ===\n");
            file_put_contents($logFile, "Time: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);
            file_put_contents($statusFile, 'running');

            // Uninstallation script
            $script = <<<BASH
#!/bin/bash

# Disable interactive prompts
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1

# Pre-emptively remove policy-rc.d if it exists
sudo rm -f /usr/sbin/policy-rc.d

echo "[1/7] Stopping mail services..."
sudo systemctl stop postfix dovecot 2>&1
sudo systemctl disable postfix dovecot 2>&1

echo ""
echo "[2/7] Purging Postfix and Dovecot packages..."
sudo apt-get purge -y postfix postfix-mysql dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql 2>&1

echo ""
echo "[3/7] Purging Roundcube package..."
sudo apt-get purge -y roundcube-core roundcube-mysql 2>&1

echo ""
echo "[4/7] Cleaning up database and configs..."
# Drop Roundcube database and users
sudo mysql -e "DROP DATABASE IF EXISTS roundcube;" 2>&1
sudo mysql -e "DROP USER IF EXISTS 'roundcube'@'localhost';" 2>&1
sudo mysql -e "DROP USER IF EXISTS 'roundcube'@'127.0.0.1';" 2>&1

# Delete configuration directories
sudo rm -rf /etc/postfix /etc/dovecot /etc/roundcube 2>&1
sudo rm -f /usr/local/nimbus/public/roundcube 2>&1

echo ""
echo "[5/7] Deleting mail directory..."
sudo rm -rf /var/mail/vhosts 2>&1
# Delete user/group vmail
sudo userdel vmail 2>&1
sudo groupdel vmail 2>&1

echo ""
echo "[6/7] Autoremoving unused packages..."
sudo apt-get autoremove -y 2>&1

echo ""
echo "[7/7] Cleaning local logs..."
echo "Uninstallation script complete."

# Remove lock file
rm -f /usr/local/nimbus/storage/logs/nimbus_install.lock
BASH;

            // Write script to temp file
            $scriptPath = '/tmp/uninstall_mailserver.sh';
            file_put_contents($scriptPath, $script);
            chmod($scriptPath, 0755);

            // Clear DB tables
            DB::table('virtual_domains')->delete();

            // Execute script detached using systemd-run
            $command = "sudo bash {$scriptPath} >> {$logFile} 2>&1; echo \$? > {$statusFile}; rm -f {$scriptPath}";
            exec("sudo systemd-run --unit=nimbus-mail-uninstall-$(date +%s) bash -c '{$command}' > /dev/null 2>&1 &");

            return response()->json([
                'success' => true,
                'message' => 'Uninstallation started',
                'logFile' => 'mailserver_install.log'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get installation log content
     */
    public function getInstallLog()
    {
        $logFile = storage_path('logs/mailserver_install.log');
        $statusFile = storage_path('logs/mailserver_install.status');
        
        $log = file_exists($logFile) ? file_get_contents($logFile) : '';
        $status = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'unknown';
        
        // Check if still running
        $isRunning = $status === 'running';
        $isComplete = $status === '0';
        $isFailed = !$isRunning && !$isComplete && $status !== 'unknown';
        
        // Auto-configure Roundcube on completion if not already configured
        if ($isComplete) {
            $roundcubeConfigured = false;
            foreach (['/etc/roundcube/config.inc.php', '/var/lib/roundcube/config/config.inc.php'] as $file) {
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    if (strpos($content, 'Nimbus Webmail') !== false && strpos($content, 'smtp_host') !== false) {
                        $roundcubeConfigured = true;
                        break;
                    }
                }
            }
            if (!$roundcubeConfigured) {
                try {
                    $this->configureRoundcube();
                } catch (\Exception $e) {
                    \Log::error("Failed to auto-configure Roundcube in getInstallLog: " . $e->getMessage());
                }
            }
        }
        
        return response()->json([
            'log' => $log,
            'status' => $status,
            'isRunning' => $isRunning,
            'isComplete' => $isComplete,
            'isFailed' => $isFailed
        ]);
    }

    /**
     * Configure Roundcube Webmail
     */
    public function configureRoundcube()
    {
        try {
            $dbUser = 'roundcube';
            $dbPass = Str::random(16);
            $dbName = 'roundcube';
            
            // Create database and user using sudo mysql (Laravel user 'nimbus' lacks global CREATE privileges)
            $dbCommands = [
                "sudo mysql -e 'CREATE DATABASE IF NOT EXISTS `" . $dbName . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'",
                "sudo mysql -e 'CREATE USER IF NOT EXISTS \"" . $dbUser . "\"@\"localhost\" IDENTIFIED BY \"" . $dbPass . "\"'",
                "sudo mysql -e 'ALTER USER \"" . $dbUser . "\"@\"localhost\" IDENTIFIED BY \"" . $dbPass . "\"'",
                "sudo mysql -e 'GRANT ALL PRIVILEGES ON `" . $dbName . "`.* TO \"" . $dbUser . "\"@\"localhost\"'",
                "sudo mysql -e 'CREATE USER IF NOT EXISTS \"" . $dbUser . "\"@\"127.0.0.1\" IDENTIFIED BY \"" . $dbPass . "\"'",
                "sudo mysql -e 'ALTER USER \"" . $dbUser . "\"@\"127.0.0.1\" IDENTIFIED BY \"" . $dbPass . "\"'",
                "sudo mysql -e 'GRANT ALL PRIVILEGES ON `" . $dbName . "`.* TO \"" . $dbUser . "\"@\"127.0.0.1\"'",
                "sudo mysql -e 'FLUSH PRIVILEGES'"
            ];
            
            foreach ($dbCommands as $cmd) {
                $output = [];
                exec($cmd . " 2>&1", $output, $code);
                if ($code !== 0) {
                    throw new \Exception("Database setup command failed: {$cmd}. Exit code: {$code}. Output: " . implode("\n", $output));
                }
            }

            // Setup roundcube database connection dynamically in Laravel config
            config(['database.connections.roundcube' => array_merge(
                config('database.connections.mysql'),
                [
                    'host' => '127.0.0.1',
                    'database' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPass
                ]
            )]);

            // Locate the schema file
            $schemaFile = null;
            $possiblePaths = [
                '/usr/share/roundcube/SQL/mysql.initial.sql',
                '/usr/share/dbconfig-common/data/roundcube/install/mysql',
                '/usr/share/roundcube/program/resources/SQL/mysql.initial.sql',
                '/var/lib/roundcube/SQL/mysql.initial.sql'
            ];
            
            $decompressedPath = storage_path('app/roundcube_mysql_initial.sql');
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $schemaFile = $path;
                    break;
                } elseif (file_exists($path . '.gz')) {
                    // Decompress gzipped schema to storage path to bypass PrivateTmp isolation
                    $output = [];
                    exec("gunzip -c " . escapeshellarg($path . '.gz') . " > " . escapeshellarg($decompressedPath) . " 2>&1", $output, $code);
                    if ($code === 0 && file_exists($decompressedPath)) {
                        $schemaFile = $decompressedPath;
                    } else {
                        throw new \Exception("Failed to decompress schema file {$path}.gz. Exit code: {$code}. Output: " . implode("\n", $output));
                    }
                    break;
                }
            }

            // Check if tables exist
            $tables = DB::connection('roundcube')->select('SHOW TABLES');
            if (empty($tables)) {
                if ($schemaFile && file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    DB::connection('roundcube')->unprepared($sql);
                    
                    // Clean up decompressed file if we created it
                    if ($schemaFile === $decompressedPath) {
                        @unlink($schemaFile);
                    }
                } else {
                    throw new \Exception("Roundcube initial SQL schema file not found. Checked: " . implode(', ', $possiblePaths));
                }
            }

            // Write config.inc.php
            $desKey = Str::random(24);
            $config = <<<PHP
<?php
\$config = [];
\$config['db_dsnw'] = 'mysql://{$dbUser}:{$dbPass}@127.0.0.1/{$dbName}';
\$config['imap_host'] = '127.0.0.1:143';
\$config['default_host'] = '127.0.0.1';
\$config['smtp_host'] = '127.0.0.1:25';
\$config['smtp_server'] = '127.0.0.1';
\$config['smtp_port'] = 25;
\$config['smtp_user'] = '';
\$config['smtp_pass'] = '';
\$config['support_url'] = '';
\$config['product_name'] = 'Nimbus Webmail';
\$config['des_key'] = '{$desKey}';
\$config['plugins'] = ['archive', 'zipdownload', 'nimbus_sso'];
\$config['skin'] = 'elastic';
PHP;

            $configPath = storage_path('app/roundcube_config.inc.php');
            if (file_put_contents($configPath, $config) === false) {
                throw new \Exception("Failed to write temporary Roundcube config file to: {$configPath}. Please check directory permissions.");
            }

            // Write custom Roundcube autologin plugin code
            $pluginCode = <<<'CODE'
<?php
class nimbus_sso extends rcube_plugin {
    public $task = 'login';

    public function init() {
        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('authenticate', array($this, 'authenticate'));
    }

    public function startup($args) {
        if (empty($_SESSION['user_id']) && isset($_GET['_sso_token'])) {
            $args['action'] = 'login';
        }
        return $args;
    }

    public function authenticate($args) {
        if (isset($_GET['_sso_token'])) {
            $token = $_GET['_sso_token'];
            
            if (preg_match('/^[a-zA-Z0-9]+$/', $token)) {
                $tokenFile = '/usr/local/nimbus/storage/app/roundcube_tokens/' . $token . '.json';
                if (file_exists($tokenFile)) {
                    $tokenData = json_decode(file_get_contents($tokenFile), true);
                    @unlink($tokenFile);
                    
                    if ($tokenData && time() <= $tokenData['expires_at']) {
                        $email = $tokenData['email'];
                        
                        $masterPwdFile = '/etc/dovecot/nimbus_master.pwd';
                        if (file_exists($masterPwdFile)) {
                            $masterPwd = trim(file_get_contents($masterPwdFile));
                            
                            $args['user'] = $email . '*nimbus_master';
                            $args['pass'] = $masterPwd;
                            $args['host'] = '127.0.0.1';
                            $args['cookiecheck'] = false;
                            $args['valid'] = true;
                        }
                    }
                }
            }
        }
        return $args;
    }
}
CODE;
            $pluginPath = storage_path('app/nimbus_sso_plugin.php');
            file_put_contents($pluginPath, $pluginCode);

            // Configure Dovecot Master User if not already configured
            if (!file_exists('/etc/dovecot/nimbus_master.pwd')) {
                $masterPass = \Illuminate\Support\Str::random(32);
                $salt = '$6$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./'), 0, 16) . '$';
                $masterHash = crypt($masterPass, $salt);
                
                $tempPwdFile = storage_path('app/nimbus_master.pwd');
                $tempUsersFile = storage_path('app/master-users');
                $tempAuthConf = storage_path('app/10-auth.conf');
                
                file_put_contents($tempPwdFile, $masterPass);
                file_put_contents($tempUsersFile, "nimbus_master:{$masterHash}\n");
                
                $authConfContent = "disable_plaintext_auth = yes\n" .
                                   "auth_mechanisms = plain login\n" .
                                   "auth_master_user_separator = *\n" .
                                   "!include auth-sql.conf.ext\n" .
                                   "!include auth-master.conf.ext\n";
                file_put_contents($tempAuthConf, $authConfContent);
                
                $dovecotCmds = [
                    "mv " . escapeshellarg($tempPwdFile) . " /etc/dovecot/nimbus_master.pwd",
                    "chmod 640 /etc/dovecot/nimbus_master.pwd",
                    "chown root:www-data /etc/dovecot/nimbus_master.pwd",
                    
                    "mv " . escapeshellarg($tempUsersFile) . " /etc/dovecot/master-users",
                    "chown root:dovecot /etc/dovecot/master-users",
                    "chmod 640 /etc/dovecot/master-users",
                    "setfacl -m u:dovecot:r,u:vmail:r /etc/dovecot/master-users 2>/dev/null || chmod 644 /etc/dovecot/master-users",
                    
                    "mv " . escapeshellarg($tempAuthConf) . " /etc/dovecot/conf.d/10-auth.conf",
                    "chmod 644 /etc/dovecot/conf.d/10-auth.conf",
                    "chown root:root /etc/dovecot/conf.d/10-auth.conf",
                    
                    "systemctl restart dovecot"
                ];
                
                foreach ($dovecotCmds as $dcmd) {
                    exec("sudo systemd-run --wait --collect bash -c " . escapeshellarg($dcmd) . " > /dev/null 2>&1");
                }
            }
            
            // Move config to /etc/roundcube/config.inc.php and set permissions
            // Running via systemd-run is required because PHP-FPM has ProtectSystem/ReadWritePaths enabled,
            // which mounts /etc as read-only inside PHP-FPM's systemd sandbox.
            $commands = [
                "mv " . escapeshellarg($configPath) . " /etc/roundcube/config.inc.php",
                "chown root:www-data /etc/roundcube/config.inc.php",
                "chmod 640 /etc/roundcube/config.inc.php",
                "chmod 755 /etc/roundcube",
                
                // Copy SSO script
                "cp /usr/local/nimbus/public/roundcube_sso.php /var/lib/roundcube/public_html/sso.php",
                "chmod 644 /var/lib/roundcube/public_html/sso.php",
                
                // Copy SSO plugin
                "mkdir -p /var/lib/roundcube/plugins/nimbus_sso",
                "mv " . escapeshellarg($pluginPath) . " /var/lib/roundcube/plugins/nimbus_sso/nimbus_sso.php",
                "chmod 644 /var/lib/roundcube/plugins/nimbus_sso/nimbus_sso.php",
                "chown -R www-data:www-data /var/lib/roundcube/plugins/nimbus_sso"
            ];
            
            $combinedCmd = implode(" && ", $commands);
            $systemdCmd = "sudo systemd-run --wait --collect bash -c " . escapeshellarg($combinedCmd) . " 2>&1";
            
            $output = [];
            exec($systemdCmd, $output, $code);
            if ($code !== 0) {
                throw new \Exception("Systemd transient execution failed. Exit code: {$code}. Output: " . implode("\n", $output));
            }
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get email-enabled domains
     */
    public function getDomains()
    {
        try {
            $domains = DB::table('virtual_domains')
                ->select('id', 'name', 'active', 'created_at')
                ->orderBy('name')
                ->get();

            // Get account counts per domain
            foreach ($domains as $domain) {
                $domain->account_count = DB::table('virtual_users')
                    ->where('domain_id', $domain->id)
                    ->count();
            }

            return response()->json(['domains' => $domains]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enable email for a domain
     */
    public function enableDomain(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|regex:/^[a-zA-Z0-9.-]+$/|max:255'
            ]);

            $domain = $request->input('domain');

            // Check if already exists
            $existing = DB::table('virtual_domains')->where('name', $domain)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'error' => 'Domain already enabled for email'
                ], 400);
            }

            // Add domain
            DB::table('virtual_domains')->insert([
                'name' => $domain,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create mail directory
            $escapedMailDir = escapeshellarg("/var/mail/vhosts/{$domain}");
            exec("sudo mkdir -p {$escapedMailDir} && sudo chown -R vmail:vmail {$escapedMailDir}");

            return response()->json([
                'success' => true,
                'message' => "Email enabled for {$domain}"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Disable email for a domain
     */
    public function disableDomain(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string'
            ]);

            DB::table('virtual_domains')
                ->where('name', $request->input('domain'))
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Domain disabled for email'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get email accounts
     */
    public function getAccounts(Request $request)
    {
        try {
            $domain = $request->input('domain');
            
            $query = DB::table('virtual_users')
                ->join('virtual_domains', 'virtual_users.domain_id', '=', 'virtual_domains.id')
                ->select(
                    'virtual_users.id',
                    'virtual_users.email',
                    'virtual_users.quota',
                    'virtual_users.active',
                    'virtual_users.created_at',
                    'virtual_domains.name as domain'
                );

            if ($domain) {
                $query->where('virtual_domains.name', $domain);
            }

            $accounts = $query->orderBy('virtual_users.email')->get();

            // Get mailbox sizes
            foreach ($accounts as $account) {
                $parts = explode('@', $account->email);
                $maildir = "/var/mail/vhosts/{$account->domain}/{$parts[0]}";
                if (is_dir($maildir)) {
                    $size = exec("sudo du -sm {$maildir} 2>/dev/null | cut -f1");
                    $account->used = intval($size);
                } else {
                    $account->used = 0;
                }
            }

            return response()->json(['accounts' => $accounts]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create email account
     */
    public function createAccount(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:64|regex:/^[a-z0-9._-]+$/',
                'domain' => 'required|string',
                'password' => 'required|string|min:8',
                'quota' => 'nullable|integer|min:10|max:10240'
            ]);

            $username = strtolower($request->input('username'));
            $domain = $request->input('domain');
            $email = "{$username}@{$domain}";
            $quota = $request->input('quota', 1024);

            // Check if domain exists
            $domainRecord = DB::table('virtual_domains')->where('name', $domain)->first();
            if (!$domainRecord) {
                return response()->json([
                    'success' => false,
                    'error' => 'Domain not enabled for email'
                ], 400);
            }

            // Check if email exists
            $exists = DB::table('virtual_users')->where('email', $email)->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Email account already exists'
                ], 400);
            }

            // Generate password hash for Dovecot (SHA512-CRYPT)
            $passwordHash = $this->generateDovecotPassword($request->input('password'));

            // Create maildir path
            $maildir = "{$domain}/{$username}/";

            // Insert user
            DB::table('virtual_users')->insert([
                'domain_id' => $domainRecord->id,
                'email' => $email,
                'password' => $passwordHash,
                'maildir' => $maildir,
                'quota' => $quota,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create maildir with all necessary folders (explicitly without braces for /bin/sh compatibility)
            $fullMaildir = "/var/mail/vhosts/{$domain}/{$username}";
            $escapedMaildir = escapeshellarg($fullMaildir);
            exec("sudo mkdir -p {$escapedMaildir}/cur {$escapedMaildir}/new {$escapedMaildir}/tmp");
            exec("sudo mkdir -p {$escapedMaildir}/.Sent/cur {$escapedMaildir}/.Sent/new {$escapedMaildir}/.Sent/tmp");
            exec("sudo mkdir -p {$escapedMaildir}/.Drafts/cur {$escapedMaildir}/.Drafts/new {$escapedMaildir}/.Drafts/tmp");
            exec("sudo mkdir -p {$escapedMaildir}/.Trash/cur {$escapedMaildir}/.Trash/new {$escapedMaildir}/.Trash/tmp");
            exec("sudo mkdir -p {$escapedMaildir}/.Junk/cur {$escapedMaildir}/.Junk/new {$escapedMaildir}/.Junk/tmp");
            exec("sudo chown -R vmail:vmail {$escapedMaildir}");

            // Send welcome email with configuration
            $this->sendWelcomeEmail($email, $domain, $request->input('password'));

            return response()->json([
                'success' => true,
                'message' => "Email account {$email} created successfully",
                'email' => $email
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send welcome email with mail client configuration
     */
    private function sendWelcomeEmail(string $email, string $domain, string $password): void
    {
        try {
            $hostname = gethostname();
            $date = date('r');
            $messageId = uniqid() . "@{$domain}";
            
            $server = strpos(strtolower($domain), 'mail.') === 0 ? $domain : 'mail.' . $domain;
            
            $body = <<<EMAIL
Welcome to your new email account!

Your email address: {$email}

=== Credentials ===
Username: {$email}
Password: {$password}

=== Email Client Configuration ===

INCOMING MAIL (IMAP)
--------------------
Server: {$server}
Port: 993
Security: SSL/TLS
Username: {$email}

INCOMING MAIL (POP3)
--------------------
Server: {$server}
Port: 995
Security: SSL/TLS
Username: {$email}

OUTGOING MAIL (SMTP)
--------------------
Server: {$server}
Port: 587
Security: STARTTLS
Username: {$email}
Authentication: Required

=== Webmail Access ===
You can also access your email via webmail at:
https://{$server}/roundcube

=== Tips ===
- Use your full email address as username
- Keep your password secure
- Enable 2FA on your email clients if available

If you have any questions, please contact your administrator.

--
This is an automated message from Nimbus Panel.
EMAIL;

            // Create the email file directly in the new folder
            $parts = explode('@', $email);
            $username = $parts[0];
            $maildir = "/var/mail/vhosts/{$domain}/{$username}/new";
            
            $emailContent = "From: Nimbus Panel <noreply@{$domain}>\r\n";
            $emailContent .= "To: {$email}\r\n";
            $emailContent .= "Subject: Welcome to your new email account!\r\n";
            $emailContent .= "Date: {$date}\r\n";
            $emailContent .= "Message-ID: <{$messageId}>\r\n";
            $emailContent .= "MIME-Version: 1.0\r\n";
            $emailContent .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $emailContent .= "\r\n";
            $emailContent .= $body;

            // Write email to maildir
            $filename = time() . '.' . uniqid() . '.' . $hostname;
            $tempFile = "/tmp/{$filename}";
            file_put_contents($tempFile, $emailContent);
            exec("sudo mv {$tempFile} {$maildir}/{$filename}");
            exec("sudo chown vmail:vmail {$maildir}/{$filename}");
        } catch (\Exception $e) {
            // Log but don't fail account creation
            \Log::warning("Failed to send welcome email: " . $e->getMessage());
        }
    }

    /**
     * Delete email account
     */
    public function deleteAccount(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email'
            ]);

            $email = $request->input('email');
            $parts = explode('@', $email);
            $username = $parts[0];
            $domain = $parts[1];

            // Delete from database
            DB::table('virtual_users')->where('email', $email)->delete();

            // Optionally delete maildir (keep for safety)
            // $maildir = "/var/mail/vhosts/{$domain}/{$username}";
            // exec("sudo rm -rf {$maildir}");

            return response()->json([
                'success' => true,
                'message' => "Email account {$email} deleted"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update email password
     */
    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:8'
            ]);

            $passwordHash = $this->generateDovecotPassword($request->input('password'));

            DB::table('virtual_users')
                ->where('email', $request->input('email'))
                ->update([
                    'password' => $passwordHash,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update mailbox quota
     */
    public function updateQuota(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'quota' => 'required|integer|min:10|max:10240'
            ]);

            DB::table('virtual_users')
                ->where('email', $request->input('email'))
                ->update([
                    'quota' => $request->input('quota'),
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Quota updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get email forwarders/aliases
     */
    public function getAliases(Request $request)
    {
        try {
            $domain = $request->input('domain');

            $query = DB::table('virtual_aliases')
                ->join('virtual_domains', 'virtual_aliases.domain_id', '=', 'virtual_domains.id')
                ->select(
                    'virtual_aliases.id',
                    'virtual_aliases.source',
                    'virtual_aliases.destination',
                    'virtual_aliases.active',
                    'virtual_domains.name as domain'
                );

            if ($domain) {
                $query->where('virtual_domains.name', $domain);
            }

            $aliases = $query->orderBy('virtual_aliases.source')->get();

            return response()->json(['aliases' => $aliases]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create email forwarder
     */
    public function createAlias(Request $request)
    {
        try {
            $request->validate([
                'source' => 'required|email',
                'destination' => 'required|email'
            ]);

            $source = $request->input('source');
            $destination = $request->input('destination');
            $domain = explode('@', $source)[1];

            // Check domain exists
            $domainRecord = DB::table('virtual_domains')->where('name', $domain)->first();
            if (!$domainRecord) {
                return response()->json([
                    'success' => false,
                    'error' => 'Domain not enabled for email'
                ], 400);
            }

            // Check if alias exists
            $exists = DB::table('virtual_aliases')
                ->where('source', $source)
                ->where('destination', $destination)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Forwarder already exists'
                ], 400);
            }

            DB::table('virtual_aliases')->insert([
                'domain_id' => $domainRecord->id,
                'source' => $source,
                'destination' => $destination,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Forwarder created: {$source} → {$destination}"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete email forwarder
     */
    public function deleteAlias(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);

            DB::table('virtual_aliases')->where('id', $request->input('id'))->delete();

            return response()->json([
                'success' => true,
                'message' => 'Forwarder deleted'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get webmail URL (for general access)
     */
    public function getWebmailUrl()
    {
        return response()->json([
            'url' => '/roundcube'
        ]);
    }

    /**
     * Generate SSO token for Roundcube auto-login
     */
    public function webmailLogin(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email'
            ]);

            $email = $request->input('email');
            
            // Check if email exists
            $user = DB::table('virtual_users')->where('email', $email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Email account not found'
                ], 404);
            }

            // Generate one-time token
            $token = \Illuminate\Support\Str::random(64);
            
            // Store token with email (expires in 60 seconds)
            $tokenFile = storage_path("app/roundcube_tokens/{$token}.json");
            $tokenDir = dirname($tokenFile);
            if (!is_dir($tokenDir)) {
                mkdir($tokenDir, 0755, true);
            }
            
            file_put_contents($tokenFile, json_encode([
                'email' => $email,
                'created_at' => time(),
                'expires_at' => time() + 60
            ]));

            return response()->json([
                'success' => true,
                'url' => "/roundcube/sso.php?token={$token}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email client settings
     */
    public function getClientSettings(Request $request)
    {
        $domain = $request->query('domain');
        
        if ($domain) {
            $server = strpos(strtolower($domain), 'mail.') === 0 ? $domain : 'mail.' . $domain;
        } else {
            $server = gethostname();
        }
        
        return response()->json([
            'incoming' => [
                'imap' => [
                    'server' => $server,
                    'port' => 993,
                    'security' => 'SSL/TLS'
                ],
                'pop3' => [
                    'server' => $server,
                    'port' => 995,
                    'security' => 'SSL/TLS'
                ]
            ],
            'outgoing' => [
                'smtp' => [
                    'server' => $server,
                    'port' => 587,
                    'security' => 'STARTTLS'
                ]
            ]
        ]);
    }

    /**
     * Generate Dovecot-compatible password hash
     */
    private function generateDovecotPassword(string $password): string
    {
        // Use SHA512-CRYPT for Dovecot
        $salt = '$6$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./'), 0, 16) . '$';
        return '{SHA512-CRYPT}' . crypt($password, $salt);
    }



    public function clearInstallLock()
    {
        try {
            $lockFile = storage_path('logs/nimbus_install.lock');
            $statusFile = storage_path('logs/mailserver_install.status');
            $logFile = storage_path('logs/mailserver_install.log');
            
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
            if (file_exists($statusFile)) {
                unlink($statusFile);
            }
            if (file_exists($logFile)) {
                unlink($logFile);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Installation lock cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
