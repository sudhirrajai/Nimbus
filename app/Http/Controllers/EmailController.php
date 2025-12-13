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
            $roundcubeInstalled = file_exists('/etc/roundcube/config.inc.php') || 
                                  file_exists('/var/lib/roundcube/config/config.inc.php');

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
     */
    public function installMailServer(Request $request)
    {
        try {
            $hostname = $request->input('hostname', gethostname());
            
            // Get MySQL credentials
            $dbHost = config('database.connections.mysql.host');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            // Installation script
            $script = <<<BASH
#!/bin/bash
set -e

export DEBIAN_FRONTEND=noninteractive

# Update package list
sudo apt-get update

# Install Postfix
echo "postfix postfix/mailname string {$hostname}" | sudo debconf-set-selections
echo "postfix postfix/main_mailer_type string 'Internet Site'" | sudo debconf-set-selections
sudo apt-get install -y postfix postfix-mysql

# Install Dovecot
sudo apt-get install -y dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql

# Install Roundcube
sudo apt-get install -y roundcube roundcube-mysql

# Create mail directory
sudo mkdir -p /var/mail/vhosts
sudo groupadd -g 5000 vmail 2>/dev/null || true
sudo useradd -g vmail -u 5000 vmail -d /var/mail 2>/dev/null || true
sudo chown -R vmail:vmail /var/mail

# Configure Postfix for virtual mailboxes
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

# Update Postfix main.cf
sudo postconf -e "myhostname = {$hostname}"
sudo postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"
sudo postconf -e "virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf"
sudo postconf -e "virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf"
sudo postconf -e "virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf"

# Configure Dovecot
sudo tee /etc/dovecot/conf.d/10-mail.conf > /dev/null << 'EOF'
mail_location = maildir:/var/mail/vhosts/%d/%n
mail_privileged_group = mail
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

# Restart services
sudo systemctl restart postfix
sudo systemctl restart dovecot
sudo systemctl enable postfix
sudo systemctl enable dovecot

echo "Mail server installation complete!"
BASH;

            // Write script to temp file
            $scriptPath = '/tmp/install_mailserver.sh';
            file_put_contents($scriptPath, $script);
            chmod($scriptPath, 0755);

            // Execute script
            $output = [];
            $returnCode = 0;
            exec("sudo bash {$scriptPath} 2>&1", $output, $returnCode);

            // Clean up
            unlink($scriptPath);

            if ($returnCode !== 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Installation failed',
                    'details' => implode("\n", $output)
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Mail server installed successfully',
                'output' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
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
                'domain' => 'required|string|max:255'
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
            $mailDir = "/var/mail/vhosts/{$domain}";
            exec("sudo mkdir -p {$mailDir} && sudo chown -R vmail:vmail {$mailDir}");

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

            // Create maildir
            $fullMaildir = "/var/mail/vhosts/{$domain}/{$username}";
            exec("sudo mkdir -p {$fullMaildir}/{cur,new,tmp}");
            exec("sudo chown -R vmail:vmail {$fullMaildir}");

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
     * Get webmail URL
     */
    public function getWebmailUrl()
    {
        return response()->json([
            'url' => '/roundcube'
        ]);
    }

    /**
     * Get email client settings
     */
    public function getClientSettings(Request $request)
    {
        $hostname = gethostname();
        
        return response()->json([
            'incoming' => [
                'imap' => [
                    'server' => $hostname,
                    'port' => 993,
                    'security' => 'SSL/TLS'
                ],
                'pop3' => [
                    'server' => $hostname,
                    'port' => 995,
                    'security' => 'SSL/TLS'
                ]
            ],
            'outgoing' => [
                'smtp' => [
                    'server' => $hostname,
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
}
