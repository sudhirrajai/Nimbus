<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

class PanelDomainController extends Controller
{
    /**
     * Set a custom domain for the Nimbus Panel
     */
    public function setup(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:253',
            'install_ssl' => 'boolean',
            'allow_ip_access' => 'boolean'
        ]);

        $domain = strtolower(trim($request->input('domain')));
        $installSsl = $request->input('install_ssl', true);
        $allowIpAccess = $request->input('allow_ip_access', true);

        // Security validation
        if (!$this->isValidDomain($domain)) {
            return response()->json(['success' => false, 'message' => 'Invalid domain name'], 400);
        }

        try {
            // 1. Create Nginx Configuration
            $this->createNginxConfig($domain);

            // 2. Install SSL with Certbot if requested
            if ($installSsl) {
                $this->installSsl($domain);
            }

            // 3. Update .env APP_URL
            $this->updateEnvUrl($domain, $installSsl);

            // 4. Save settings to DB
            Setting::updateOrCreate(['key' => 'panel_domain'], ['value' => $domain]);
            Setting::updateOrCreate(['key' => 'panel_ssl'], ['value' => $installSsl ? '1' : '0']);
            Setting::updateOrCreate(['key' => 'allow_ip_access'], ['value' => $allowIpAccess ? '1' : '0']);

            return response()->json([
                'success' => true,
                'message' => "Panel domain configured successfully. You can now access Nimbus at https://{$domain}"
            ]);

        } catch (\Exception $e) {
            Log::error("Panel Domain Setup Failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create the Nginx vhost for the panel
     */
    private function createNginxConfig($domain)
    {
        $panelPath = '/usr/local/nimbus/public';
        $configPath = "/etc/nginx/sites-available/nimbus_panel";
        $enabledPath = "/etc/nginx/sites-enabled/nimbus_panel";
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        $configContent = "
server {
    listen 80;
    server_name " . $domain . ";
    root " . $panelPath . ";

    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php{$phpVersion}-fpm-nimbus.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}";

        // Write to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'nimbus_vhost');
        File::put($tempFile, $configContent);

        // Move to Nginx sites-available using sudo
        $this->executeSudo("cp " . escapeshellarg($tempFile) . " " . escapeshellarg($configPath));
        $this->executeSudo("ln -sf " . escapeshellarg($configPath) . " " . escapeshellarg($enabledPath));
        
        // Test and reload Nginx
        $this->executeSudo("nginx -t");
        $this->executeSudo("systemctl reload nginx");

        unlink($tempFile);
    }

    /**
     * Install SSL using Certbot
     */
    private function installSsl($domain)
    {
        // Check if certbot is installed
        $check = shell_exec("which certbot");
        if (!$check) {
            throw new \Exception("Certbot is not installed on the server.");
        }

        // Run certbot (non-interactive, automatic redirect)
        $this->executeSudo("certbot --nginx -d " . escapeshellarg($domain) . " --non-interactive --agree-tos --register-unsafely-without-email --redirect");
    }

    /**
     * Update the APP_URL in .env
     */
    private function updateEnvUrl($domain, $isSsl)
    {
        $protocol = $isSsl ? 'https' : 'http';
        $newUrl = "{$protocol}://{$domain}";
        $envPath = base_path('.env');

        if (File::exists($envPath)) {
            $content = File::get($envPath);
            $content = preg_replace('/^APP_URL=.*$/m', "APP_URL={$newUrl}", $content);
            File::put($envPath, $content);
        }
    }

    private function isValidDomain($domain)
    {
        return preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $domain) && !str_contains($domain, ' ');
    }

    private function executeSudo($command)
    {
        $output = [];
        $returnCode = 0;
        exec("sudo $command 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Command failed: " . implode("\n", $output));
        }

        return $output;
    }
}
