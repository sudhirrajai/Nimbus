<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class NodeController extends Controller
{
    private string $nPath = '/usr/bin/n';
    private string $versionsDir = '/usr/local/n/versions/node';

    /**
     * Display Node.js Version Manager page
     */
    public function index()
    {
        return Inertia::render('Node/Index');
    }

    /**
     * Get Node.js versions status
     */
    public function getStatus()
    {
        try {
            $nodePath = trim(shell_exec('PATH=$PATH:/usr/local/bin:/usr/bin which node 2>/dev/null') ?: '');
            if (!$nodePath && file_exists('/usr/local/bin/node')) $nodePath = '/usr/local/bin/node';
            if (!$nodePath && file_exists('/usr/bin/node')) $nodePath = '/usr/bin/node';

            $npmPath = trim(shell_exec('PATH=$PATH:/usr/local/bin:/usr/bin which npm 2>/dev/null') ?: '');
            if (!$npmPath && file_exists('/usr/local/bin/npm')) $npmPath = '/usr/local/bin/npm';
            if (!$npmPath && file_exists('/usr/bin/npm')) $npmPath = '/usr/bin/npm';

            $nPath = trim(shell_exec('PATH=$PATH:/usr/local/bin:/usr/bin which n 2>/dev/null') ?: '');
            if (!$nPath && file_exists('/usr/local/bin/n')) $nPath = '/usr/local/bin/n';
            if (!$nPath && file_exists('/usr/bin/n')) $nPath = '/usr/bin/n';
            if (!$nPath && file_exists(base_path('scripts/n'))) {
                exec('sudo cp ' . escapeshellarg(base_path('scripts/n')) . ' /usr/local/bin/n && sudo chmod 755 /usr/local/bin/n && sudo ln -sf /usr/local/bin/n /usr/bin/n 2>/dev/null');
                if (file_exists('/usr/local/bin/n')) $nPath = '/usr/local/bin/n';
            }

            $rawNodeVersion = $nodePath ? trim(shell_exec("{$nodePath} -v 2>/dev/null") ?: '') : '';
            $activeVersion = ltrim($rawNodeVersion, 'v');
            $npmVersion = $npmPath ? trim(shell_exec("{$npmPath} -v 2>/dev/null") ?: '') : '';

            // Detect installed versions from /usr/local/n/versions/node
            $installedVersions = [];
            if (File::isDirectory($this->versionsDir)) {
                $dirs = File::directories($this->versionsDir);
                foreach ($dirs as $dir) {
                    $ver = basename($dir);
                    $cleanVer = ltrim($ver, 'v');
                    $major = explode('.', $cleanVer)[0] ?? '';
                    $installedVersions[] = [
                        'version' => $cleanVer,
                        'major' => $major,
                        'path' => $dir . '/bin/node',
                        'npm_path' => $dir . '/bin/npm',
                        'is_active' => ($cleanVer === $activeVersion),
                        'is_lts' => in_array($major, ['18', '20', '22']),
                    ];
                }
            }

            // If n ls has anything else or versions dir was empty
            if (empty($installedVersions) && !empty($activeVersion)) {
                $installedVersions[] = [
                    'version' => $activeVersion,
                    'major' => explode('.', $activeVersion)[0] ?? '',
                    'path' => $nodePath,
                    'npm_path' => $npmPath,
                    'is_active' => true,
                    'is_lts' => in_array(explode('.', $activeVersion)[0] ?? '', ['18', '20', '22']),
                ];
            }

            // Sort installed versions descending by semver
            usort($installedVersions, function ($a, $b) {
                return version_compare($b['version'], $a['version']);
            });

            // Available popular LTS versions for 1-click install
            $availableLts = [
                [
                    'major' => '22',
                    'name' => 'Node.js 22 LTS (Active)',
                    'codename' => 'Jod',
                    'description' => 'Current recommended LTS release with latest ECMAScript features and V8 12.4.',
                    'installed' => collect($installedVersions)->contains('major', '22'),
                ],
                [
                    'major' => '20',
                    'name' => 'Node.js 20 LTS (Maintenance)',
                    'codename' => 'Iron',
                    'description' => 'Stable enterprise LTS release with V8 11.3 and widespread package support.',
                    'installed' => collect($installedVersions)->contains('major', '20'),
                ],
                [
                    'major' => '18',
                    'name' => 'Node.js 18 LTS (Maintenance)',
                    'codename' => 'Hydrogen',
                    'description' => 'Legacy LTS release for older applications requiring Node 18 runtime.',
                    'installed' => collect($installedVersions)->contains('major', '18'),
                ],
            ];

            return response()->json([
                'node_installed' => !empty($nodePath),
                'active_version' => $activeVersion,
                'active_path' => $nodePath,
                'npm_version' => $npmVersion,
                'n_installed' => !empty($nPath),
                'installed_versions' => $installedVersions,
                'available_lts' => $availableLts,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get Node.js status: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start background installation of a Node.js version using n
     */
    public function install(Request $request)
    {
        try {
            $request->validate([
                'version' => ['required', 'string', 'regex:/^(18|20|22|24|lts|latest|[0-9]+(\.[0-9]+(\.[0-9]+)?)?)$/']
            ]);

            $version = $request->input('version');
            $lockFile = storage_path('logs/node_install.lock');

            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json([
                    'error' => "Another Node.js installation is currently running: {$lockContent}"
                ], 409);
            }

            file_put_contents($lockFile, "Installing Node.js {$version}");

            @unlink('/tmp/nimbus_node_install_done');
            @unlink('/tmp/nimbus_node_install_failed');

            $logFile = storage_path('logs/node_install.log');
            file_put_contents($logFile, "=== Node.js {$version} Installation Started ===\n");
            file_put_contents($logFile, "Time: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);

            $scriptPath = storage_path('app/install_node_' . preg_replace('/[^a-zA-Z0-9]/', '_', $version) . '.sh');
            $bundledN = base_path('scripts/n');

            $scriptContent = <<<BASH
#!/bin/bash
set -o pipefail
export N_PREFIX=/usr/local
export PATH=\$N_PREFIX/bin:/usr/local/bin:/usr/bin:/bin:\$PATH

echo "Checking required system utilities..."
if ! command -v xz >/dev/null 2>&1 || ! command -v curl >/dev/null 2>&1 || ! command -v tar >/dev/null 2>&1; then
    echo "Installing xz-utils, curl, and tar for binary extraction..."
    sudo apt-get update -qq 2>&1 && sudo apt-get install -y -qq xz-utils curl tar 2>&1
fi

# Ensure 'n' Node version manager is available
if ! command -v n >/dev/null 2>&1 && [ ! -x /usr/local/bin/n ] && [ ! -x /usr/bin/n ]; then
    echo "Node version manager (n) not found. Setting up 'n'..."
    if [ -f "{$bundledN}" ]; then
        sudo cp "{$bundledN}" /usr/local/bin/n 2>&1
        sudo chmod 755 /usr/local/bin/n 2>&1
        sudo ln -sf /usr/local/bin/n /usr/bin/n 2>&1
        echo "✓ Installed 'n' from bundled Nimbus script."
    elif [ -f /usr/local/nimbus/scripts/n ]; then
        sudo cp /usr/local/nimbus/scripts/n /usr/local/bin/n 2>&1
        sudo chmod 755 /usr/local/bin/n 2>&1
        sudo ln -sf /usr/local/bin/n /usr/bin/n 2>&1
        echo "✓ Installed 'n' from /usr/local/nimbus/scripts/n."
    elif command -v npm >/dev/null 2>&1; then
        echo "Installing 'n' via npm..."
        sudo npm install -g n 2>&1
    else
        echo "Downloading 'n' from official repository..."
        sudo curl -fsSL https://raw.githubusercontent.com/tj/n/master/bin/n -o /usr/local/bin/n 2>&1 || \
        sudo curl -fsSL https://github.com/tj/n/raw/master/bin/n -o /usr/local/bin/n 2>&1 || \
        sudo wget -qO /usr/local/bin/n https://raw.githubusercontent.com/tj/n/master/bin/n 2>&1
        sudo chmod 755 /usr/local/bin/n 2>&1
        sudo ln -sf /usr/local/bin/n /usr/bin/n 2>&1
    fi
fi

N_EXEC=""
if [ -x /usr/local/bin/n ]; then
    N_EXEC="/usr/local/bin/n"
elif [ -x /usr/bin/n ]; then
    N_EXEC="/usr/bin/n"
elif command -v n >/dev/null 2>&1; then
    N_EXEC="\$(command -v n)"
fi

if [ -z "\$N_EXEC" ]; then
    echo "ERROR: Failed to initialize Node version manager (n)."
    touch /tmp/nimbus_node_install_failed
    rm -f "{$lockFile}"
    exit 1
fi

sudo mkdir -p /usr/local/n/versions/node 2>&1
sudo chmod -R 775 /usr/local/n 2>&1

echo "Installing Node.js {$version} via \$N_EXEC..."
if ! sudo \$N_EXEC {$version} 2>&1; then
    echo "ERROR: Node.js {$version} download/install failed."
    touch /tmp/nimbus_node_install_failed
    rm -f "{$lockFile}"
    exit 1
fi

echo "Ensuring global symlinks..."
sudo ln -sf /usr/local/bin/node /usr/bin/node 2>&1
sudo ln -sf /usr/local/bin/npm /usr/bin/npm 2>&1
sudo ln -sf /usr/local/bin/npx /usr/bin/npx 2>&1
sudo ln -sf "\$N_EXEC" /usr/bin/n 2>&1
sudo ln -sf "\$N_EXEC" /usr/local/bin/n 2>&1

echo "Verifying installation..."
NODE_VER=\$(/usr/local/bin/node -v 2>&1 || node -v 2>&1 || echo "")
NPM_VER=\$(/usr/local/bin/npm -v 2>&1 || npm -v 2>&1 || echo "")

if [ -z "\$NODE_VER" ] || [[ "\$NODE_VER" == *"No such file"* ]] || [[ "\$NODE_VER" == *"command not found"* ]]; then
    echo "ERROR: Node.js executable not functional after installation."
    touch /tmp/nimbus_node_install_failed
    rm -f "{$lockFile}"
    exit 1
fi

echo "Node version verified: \$NODE_VER"
echo "npm version verified:  \$NPM_VER"
echo "Node.js {$version} installation finished successfully."
touch /tmp/nimbus_node_install_done
rm -f "{$lockFile}"
BASH;

            file_put_contents($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            // Execute script in background
            $cmd = "bash " . escapeshellarg($scriptPath) . " >> " . escapeshellarg($logFile) . " 2>&1 &";
            exec($cmd);

            return response()->json([
                'message' => "Node.js {$version} installation started in background.",
                'status' => 'installing'
            ]);
        } catch (\Exception $e) {
            Log::error("Node.js install error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Poll install status
     */
    public function getInstallStatus()
    {
        $lockFile = storage_path('logs/node_install.lock');
        $logFile = storage_path('logs/node_install.log');
        $doneFile = '/tmp/nimbus_node_install_done';
        $failedFile = '/tmp/nimbus_node_install_failed';

        $isInstalling = file_exists($lockFile);
        $isDone = file_exists($doneFile);
        $isFailed = file_exists($failedFile);

        $logs = '';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $logs = implode('', array_slice($lines, -40));
        }

        if ($isFailed && !$isInstalling) {
            @unlink($failedFile);
            @unlink($doneFile);
            return response()->json([
                'status' => 'failed',
                'message' => 'Node.js installation failed. Check the logs below.',
                'logs' => $logs
            ]);
        }

        if ($isDone && !$isInstalling) {
            @unlink($doneFile);
            @unlink($failedFile);
            return response()->json([
                'status' => 'completed',
                'message' => 'Node.js installation completed successfully.',
                'logs' => $logs
            ]);
        }

        return response()->json([
            'status' => $isInstalling ? 'installing' : 'idle',
            'message' => $isInstalling ? (file_get_contents($lockFile) ?: 'Installing...') : 'Ready',
            'logs' => $logs
        ]);
    }

    /**
     * Switch global default Node.js version
     */
    public function switchGlobal(Request $request)
    {
        try {
            $request->validate([
                'version' => ['required', 'string', 'regex:/^[0-9]+(\.[0-9]+(\.[0-9]+)?)?$/']
            ]);

            $version = $request->input('version');
            $escaped = escapeshellarg($version);
            $nBin = $this->ensureN();

            $output = [];
            $ret = 0;
            exec("sudo {$nBin} {$escaped} 2>&1", $output, $ret);

            if ($ret !== 0) {
                return response()->json(['error' => 'Failed to switch version: ' . implode("\n", $output)], 500);
            }

            // Refresh symlinks
            exec("sudo ln -sf /usr/local/bin/node /usr/bin/node; sudo ln -sf /usr/local/bin/npm /usr/bin/npm; sudo ln -sf /usr/local/bin/npx /usr/bin/npx; sudo ln -sf {$nBin} /usr/bin/n 2>&1");

            $newVer = trim(shell_exec('/usr/local/bin/node -v 2>/dev/null') ?: '');

            return response()->json([
                'message' => "Successfully switched active Node.js to {$newVer}",
                'active_version' => ltrim($newVer, 'v')
            ]);
        } catch (\Exception $e) {
            Log::error("Node.js switch error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Uninstall a Node.js version
     */
    public function uninstall(Request $request)
    {
        try {
            $request->validate([
                'version' => ['required', 'string', 'regex:/^[0-9]+(\.[0-9]+(\.[0-9]+)?)?$/']
            ]);

            $version = $request->input('version');
            $activeVer = ltrim(trim(shell_exec('node -v 2>/dev/null') ?: ''), 'v');

            if ($version === $activeVer) {
                return response()->json(['error' => "Cannot remove the currently active Node.js version ({$version}). Switch to another version first."], 400);
            }

            $escaped = escapeshellarg($version);
            $nBin = $this->ensureN();
            $output = [];
            $ret = 0;
            exec("sudo {$nBin} rm {$escaped} 2>&1", $output, $ret);

            if ($ret !== 0) {
                return response()->json(['error' => 'Failed to remove version: ' . implode("\n", $output)], 500);
            }

            return response()->json([
                'message' => "Node.js {$version} removed successfully."
            ]);
        } catch (\Exception $e) {
            Log::error("Node.js uninstall error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ensure n version manager is installed and executable
     */
    private function ensureN(): string
    {
        $nPath = trim(shell_exec('PATH=$PATH:/usr/local/bin:/usr/bin which n 2>/dev/null') ?: '');
        if ($nPath && is_executable($nPath)) {
            return $nPath;
        }

        if (file_exists('/usr/local/bin/n') && is_executable('/usr/local/bin/n')) {
            return '/usr/local/bin/n';
        }

        if (file_exists('/usr/bin/n') && is_executable('/usr/bin/n')) {
            return '/usr/bin/n';
        }

        $bundled = base_path('scripts/n');
        if (file_exists($bundled)) {
            exec("sudo cp " . escapeshellarg($bundled) . " /usr/local/bin/n && sudo chmod 755 /usr/local/bin/n && sudo ln -sf /usr/local/bin/n /usr/bin/n 2>&1");
            if (file_exists('/usr/local/bin/n')) {
                return '/usr/local/bin/n';
            }
        }

        return 'n';
    }
}
