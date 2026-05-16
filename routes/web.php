<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\PhpController;
use App\Http\Controllers\NginxController;
use App\Http\Controllers\SslController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\GitDeploymentController;

// Auth routes (public)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('auth.login');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login.store');
    Route::get('/setup', [AuthController::class, 'showSetup'])->name('auth.setup');
    Route::post('/setup', [AuthController::class, 'setup'])->name('auth.setup.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth');

// Redirect root to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// ═══════════════════════════════════════════════════════════════
// Protected routes — all authenticated users
// ═══════════════════════════════════════════════════════════════
Route::middleware(['auth', \App\Http\Middleware\EnsureSetupComplete::class])->group(function () {

    // Dashboard — all users can access
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/stats', [DashboardController::class, 'getStats'])->name('stats');
    });

    // Global Search
    Route::get('/api/search', [\App\Http\Controllers\SearchController::class, 'search'])->name('api.search');

    // Profile — all users can manage their own profile
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ProfileController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\ProfileController::class, 'updateProfile'])->name('update');
        Route::post('/password', [\App\Http\Controllers\ProfileController::class, 'changePassword'])->name('password');
    });

    // ─── DOMAIN-SCOPED PAGES ─────────────────────────────────────
    // These pages are accessible to ALL authenticated users.
    // Controllers filter data based on user's assigned websites.
    // Root sees everything, others see only their assigned domains.

    // Domains page & API (controller filters by user assignments)
    Route::get('/domains', function () {
        return Inertia::render('Domains/Index');
    })->name('domains.list');

    Route::prefix('domains')->group(function () {
        Route::get('/api', [DomainController::class, 'index'])->name('domain.index');
        Route::get('/api/{domain}/details', [DomainController::class, 'getDomainDetails'])->name('domain.details');
    });

    // Domain create/update/delete — root+admin only
    Route::middleware(['role:root,admin'])->prefix('domains')->group(function () {
        Route::post('/', [DomainController::class, 'store'])->name('domain.store');
        Route::put('/{domain}', [DomainController::class, 'update'])->name('domain.update');
        Route::put('/{domain}/root', [DomainController::class, 'updateRoot'])->name('domain.update.root');
        Route::delete('/{domain}', [DomainController::class, 'destroy'])->name('domain.destroy');
    });

    // File Manager — domain-scoped access via middleware
    Route::middleware(['domain.access'])->prefix('file-manager')->name('file-manager.')->group(function () {
        Route::get('/{domain}', [FileManagerController::class, 'index'])->name('index');
        Route::post('/{domain}/list', [FileManagerController::class, 'list'])->name('list');
        Route::post('/{domain}/read', [FileManagerController::class, 'read'])->name('read');
        Route::post('/{domain}/save', [FileManagerController::class, 'save'])->name('save');
        Route::post('/{domain}/create-file', [FileManagerController::class, 'createFile'])->name('create-file');
        Route::post('/{domain}/create-directory', [FileManagerController::class, 'createDirectory'])->name('create-directory');
        Route::post('/{domain}/delete', [FileManagerController::class, 'delete'])->name('delete');
        Route::post('/{domain}/delete-multiple', [FileManagerController::class, 'deleteMultiple'])->name('delete-multiple');
        Route::post('/{domain}/rename', [FileManagerController::class, 'rename'])->name('rename');
        Route::post('/{domain}/copy', [FileManagerController::class, 'copy'])->name('copy');
        Route::post('/{domain}/move', [FileManagerController::class, 'move'])->name('move');
        Route::post('/{domain}/chmod', [FileManagerController::class, 'chmod'])->name('chmod');
        Route::post('/{domain}/zip', [FileManagerController::class, 'zip'])->name('zip');
        Route::post('/{domain}/extract', [FileManagerController::class, 'extract'])->name('extract');
        Route::post('/{domain}/upload', [FileManagerController::class, 'upload'])->name('upload');
        Route::get('/{domain}/download', [FileManagerController::class, 'download'])->name('download');
        Route::post('/{domain}/git/status', [FileManagerController::class, 'gitStatus'])->name('git.status');
        Route::post('/{domain}/git/action', [FileManagerController::class, 'gitAction'])->name('git.action');
        Route::post('/{domain}/git/token', [FileManagerController::class, 'saveGitToken'])->name('git.token.save');
        Route::get('/{domain}/git/token', [FileManagerController::class, 'getGitToken'])->name('git.token.get');
        Route::post('/{domain}/search', [FileManagerController::class, 'search'])->name('search');
        // Web Terminal
        Route::post('/{domain}/terminal/execute', [\App\Http\Controllers\TerminalController::class, 'execute'])->name('terminal.execute');
    });

    // Git Deployments — accessible to users with 'deployments' permission (controller filters)
    Route::get('/deployments', [GitDeploymentController::class, 'index'])->name('deployments.index');
    Route::get('/deployments/create', [GitDeploymentController::class, 'create'])->name('deployments.create');
    Route::get('/deployments/{id}/view-logs', [GitDeploymentController::class, 'showLogs'])->name('deployments.view-logs');

    Route::prefix('deployments')->name('deployments.')->group(function () {
        Route::get('/list', [GitDeploymentController::class, 'list'])->name('list');
        Route::get('/domains', [GitDeploymentController::class, 'getDomains'])->name('domains');
        Route::post('/', [GitDeploymentController::class, 'store'])->name('store');
        Route::post('/validate-repo', [GitDeploymentController::class, 'validateRepo'])->name('validate-repo');
        Route::post('/branches', [GitDeploymentController::class, 'getBranches'])->name('branches');
        Route::get('/ssh-key', [GitDeploymentController::class, 'getServerSshKey'])->name('ssh-key');
        Route::get('/{id}/status', [GitDeploymentController::class, 'status'])->name('status');
        Route::get('/{id}/logs', [GitDeploymentController::class, 'logs'])->name('logs');
        Route::post('/{id}/deploy', [GitDeploymentController::class, 'deploy'])->name('deploy');
        Route::post('/{id}/redeploy', [GitDeploymentController::class, 'redeploy'])->name('redeploy');
        Route::delete('/{id}', [GitDeploymentController::class, 'destroy'])->name('destroy');
        Route::get('/blacklist', [GitDeploymentController::class, 'getBlacklist'])->name('blacklist');
        Route::post('/blacklist', [GitDeploymentController::class, 'updateBlacklist'])->name('blacklist.update');
    });

    // SSL Certificates — accessible to users with 'ssl' permission (controller filters)
    Route::prefix('ssl')->name('ssl.')->group(function () {
        Route::get('/', [SslController::class, 'index'])->name('index');
        Route::get('/domains', [SslController::class, 'getDomains'])->name('domains');
        Route::get('/certbot-status', [SslController::class, 'certbotStatus'])->name('certbot-status');
        Route::post('/install-certbot', [SslController::class, 'installCertbotAction'])->name('install-certbot');
        Route::post('/install', [SslController::class, 'installCertificate'])->name('install');
        Route::post('/renew', [SslController::class, 'renewCertificate'])->name('renew');
        Route::post('/renew-all', [SslController::class, 'renewAll'])->name('renew-all');
        Route::post('/remove', [SslController::class, 'removeCertificate'])->name('remove');
    });

    // Database Management — accessible to users with 'database' permission (controller filters)
    Route::prefix('database')->name('database.')->group(function () {
        Route::get('/', [DatabaseController::class, 'index'])->name('index');
        Route::get('/status', [DatabaseController::class, 'getStatus'])->name('status');
        Route::post('/install-viewer', [DatabaseController::class, 'installDatabaseViewer'])->name('install-viewer');
        Route::post('/reinstall-viewer', [DatabaseController::class, 'reinstallDatabaseViewer'])->name('reinstall-viewer');
        Route::post('/clear-lock', [DatabaseController::class, 'clearInstallLock'])->name('clear-lock');
        Route::get('/install-status', [DatabaseController::class, 'getInstallStatus'])->name('install-status');
        Route::get('/credentials/download', [DatabaseController::class, 'downloadCredentials'])->name('credentials.download');
        Route::get('/list', [DatabaseController::class, 'getDatabases'])->name('list');
        Route::get('/users', [DatabaseController::class, 'getUsers'])->name('users');
        Route::post('/create', [DatabaseController::class, 'createDatabase'])->name('create');
        Route::post('/delete', [DatabaseController::class, 'deleteDatabase'])->name('delete');
        Route::post('/user/create', [DatabaseController::class, 'createUser'])->name('user.create');
        Route::post('/user/delete', [DatabaseController::class, 'deleteUser'])->name('user.delete');
        Route::post('/user/assign', [DatabaseController::class, 'assignUser'])->name('user.assign');
        Route::post('/user/permissions', [DatabaseController::class, 'updatePermissions'])->name('user.permissions');
        Route::post('/user/password', [DatabaseController::class, 'updatePassword'])->name('user.password');
        Route::post('/viewer/access', [DatabaseController::class, 'getDatabaseViewerUrl'])->name('viewer.access');
        Route::get('/viewer/sso', [DatabaseController::class, 'openDatabaseViewerSSO'])->name('viewer.sso');
        Route::get('/viewer/signon/{token}', [DatabaseController::class, 'databaseViewerSignon'])->name('viewer.signon');
        Route::get('/viewer-view', [DatabaseController::class, 'DatabaseViewerView'])->name('viewer.view');
    });

    // WordPress Management — accessible to users with 'wordpress' permission (controller filters)
    Route::prefix('wordpress')->name('wordpress.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WordPressController::class, 'index'])->name('index');
        Route::get('/list', [\App\Http\Controllers\WordPressController::class, 'list'])->name('list');
        Route::post('/scan', [\App\Http\Controllers\WordPressController::class, 'scan'])->name('scan');
        Route::post('/install', [\App\Http\Controllers\WordPressController::class, 'install'])->name('install');
        Route::get('/{id}/details', [\App\Http\Controllers\WordPressController::class, 'details'])->name('details');
        Route::post('/{id}/password', [\App\Http\Controllers\WordPressController::class, 'changePassword'])->name('password');
        Route::post('/{id}/update-core', [\App\Http\Controllers\WordPressController::class, 'updateCore'])->name('update-core');
        Route::post('/{id}/update-plugins', [\App\Http\Controllers\WordPressController::class, 'updatePlugins'])->name('update-plugins');
        Route::post('/{id}/toggle-plugin', [\App\Http\Controllers\WordPressController::class, 'togglePlugin'])->name('toggle-plugin');
        Route::post('/{id}/auto-login', [\App\Http\Controllers\WordPressController::class, 'autoLogin'])->name('auto-login');
        Route::delete('/{id}', [\App\Http\Controllers\WordPressController::class, 'delete'])->name('delete');
    });

    // DNS Management — domain-scoped access via Cloudflare
    Route::prefix('dns')->name('dns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CloudflareDnsController::class, 'index'])->name('index');
        Route::get('/domains', [\App\Http\Controllers\CloudflareDnsController::class, 'getDomains'])->name('domains');
        Route::post('/{domain}/credentials', [\App\Http\Controllers\CloudflareDnsController::class, 'saveCredentials'])->name('credentials');
        Route::get('/{domain}/records', [\App\Http\Controllers\CloudflareDnsController::class, 'getRecords'])->name('records.list');
        Route::post('/{domain}/records', [\App\Http\Controllers\CloudflareDnsController::class, 'createRecord'])->name('records.create');
        Route::put('/{domain}/records/{recordId}', [\App\Http\Controllers\CloudflareDnsController::class, 'updateRecord'])->name('records.update');
        Route::delete('/{domain}/records/{recordId}', [\App\Http\Controllers\CloudflareDnsController::class, 'deleteRecord'])->name('records.delete');
    });

    // ─── ROOT & ADMIN — System Administration ───────────────────────
    Route::middleware(['role:root,admin'])->group(function () {

        // PHP Configuration routes
        Route::prefix('php')->name('php.')->group(function () {
            Route::get('/', [PhpController::class, 'index'])->name('index');
            Route::get('/info', [PhpController::class, 'getInfo'])->name('info');
            Route::post('/read', [PhpController::class, 'readIni'])->name('read');
            Route::post('/save', [PhpController::class, 'saveIni'])->name('save');
            Route::post('/update-setting', [PhpController::class, 'updateSetting'])->name('update-setting');
            Route::post('/restart', [PhpController::class, 'restartPhp'])->name('restart');
            Route::post('/sync-nginx-limits', [PhpController::class, 'syncNginxLimits'])->name('sync-nginx-limits');
        });

        // Nginx Configuration routes
        Route::prefix('nginx')->name('nginx.')->group(function () {
            Route::get('/', [NginxController::class, 'index'])->name('index');
            Route::get('/domains', [NginxController::class, 'getDomains'])->name('domains');
            Route::post('/config/read', [NginxController::class, 'getConfig'])->name('config.read');
            Route::post('/config/save', [NginxController::class, 'saveConfig'])->name('config.save');
            Route::post('/test', [NginxController::class, 'testConfig'])->name('test');
            Route::post('/reload', [NginxController::class, 'reloadNginx'])->name('reload');
            Route::post('/toggle', [NginxController::class, 'toggleDomain'])->name('toggle');
        });

        // Email Management routes
        Route::prefix('email')->name('email.')->group(function () {
            Route::get('/', [EmailController::class, 'index'])->name('index');
            Route::get('/status', [EmailController::class, 'getStatus'])->name('status');
            Route::post('/install', [EmailController::class, 'installMailServer'])->name('install');
            Route::get('/install-log', [EmailController::class, 'getInstallLog'])->name('install-log');
            Route::get('/domains', [EmailController::class, 'getDomains'])->name('domains');
            Route::post('/domain/enable', [EmailController::class, 'enableDomain'])->name('domain.enable');
            Route::post('/domain/disable', [EmailController::class, 'disableDomain'])->name('domain.disable');
            Route::get('/accounts', [EmailController::class, 'getAccounts'])->name('accounts');
            Route::post('/account/create', [EmailController::class, 'createAccount'])->name('account.create');
            Route::post('/account/delete', [EmailController::class, 'deleteAccount'])->name('account.delete');
            Route::post('/account/password', [EmailController::class, 'updatePassword'])->name('account.password');
            Route::post('/account/quota', [EmailController::class, 'updateQuota'])->name('account.quota');
            Route::get('/aliases', [EmailController::class, 'getAliases'])->name('aliases');
            Route::post('/alias/create', [EmailController::class, 'createAlias'])->name('alias.create');
            Route::post('/alias/delete', [EmailController::class, 'deleteAlias'])->name('alias.delete');
            Route::get('/webmail', [EmailController::class, 'getWebmailUrl'])->name('webmail');
            Route::post('/webmail-login', [EmailController::class, 'webmailLogin'])->name('webmail-login');
            Route::get('/client-settings', [EmailController::class, 'getClientSettings'])->name('client-settings');
            Route::post('/configure-roundcube', [EmailController::class, 'configureRoundcube'])->name('configure-roundcube');
        });

        // Supervisor Management routes
        Route::prefix('supervisor')->name('supervisor.')->group(function () {
            Route::get('/', [SupervisorController::class, 'index'])->name('index');
            Route::get('/status', [SupervisorController::class, 'getStatus'])->name('status');
            Route::post('/install', [SupervisorController::class, 'install'])->name('install');
            Route::get('/install-log', [SupervisorController::class, 'getInstallLog'])->name('install-log');
            Route::get('/processes', [SupervisorController::class, 'getProcesses'])->name('processes');
            Route::post('/start', [SupervisorController::class, 'startProcess'])->name('start');
            Route::post('/stop', [SupervisorController::class, 'stopProcess'])->name('stop');
            Route::post('/restart', [SupervisorController::class, 'restartProcess'])->name('restart');
            Route::post('/create', [SupervisorController::class, 'createProcess'])->name('create');
            Route::post('/delete', [SupervisorController::class, 'deleteProcess'])->name('delete');
            Route::get('/logs', [SupervisorController::class, 'viewLogs'])->name('logs');
            Route::post('/reload', [SupervisorController::class, 'reloadConfig'])->name('reload');
            Route::get('/users', [SupervisorController::class, 'getSystemUsers'])->name('users');
            Route::get('/config', [SupervisorController::class, 'getProcessConfig'])->name('config');
            Route::post('/update', [SupervisorController::class, 'updateProcess'])->name('update');
            Route::post('/start-all', [SupervisorController::class, 'startAll'])->name('start-all');
            Route::post('/stop-all', [SupervisorController::class, 'stopAll'])->name('stop-all');
            Route::post('/restart-all', [SupervisorController::class, 'restartAll'])->name('restart-all');
            Route::get('/projects', [SupervisorController::class, 'getProjects'])->name('projects');
        });

        // Cron Jobs routes
        Route::prefix('cron')->name('cron.')->group(function () {
            Route::get('/', [CronController::class, 'index'])->name('index');
            Route::get('/jobs', [CronController::class, 'getJobs'])->name('jobs');
            Route::post('/create', [CronController::class, 'createJob'])->name('create');
            Route::post('/update', [CronController::class, 'updateJob'])->name('update');
            Route::post('/delete', [CronController::class, 'deleteJob'])->name('delete');
            Route::post('/run', [CronController::class, 'runNow'])->name('run');
            Route::post('/describe', [CronController::class, 'describeSchedule'])->name('describe');
        });

        // Logs routes
        Route::prefix('logs')->name('logs.')->group(function () {
            Route::get('/', [\App\Http\Controllers\LogsController::class, 'index'])->name('index');
            Route::get('/files', [\App\Http\Controllers\LogsController::class, 'getLogFiles'])->name('files');
            Route::get('/read', [\App\Http\Controllers\LogsController::class, 'readLog'])->name('read');
            Route::post('/clear', [\App\Http\Controllers\LogsController::class, 'clearLog'])->name('clear');
            Route::get('/download', [\App\Http\Controllers\LogsController::class, 'downloadLog'])->name('download');
        });

        // Resource usage routes
        Route::prefix('resources')->name('resources.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ResourceController::class, 'index'])->name('index');
            Route::get('/usage', [\App\Http\Controllers\ResourceController::class, 'getUsage'])->name('usage');
        });

        // Settings routes
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ProfileController::class, 'settings'])->name('index');
            Route::get('/data', [\App\Http\Controllers\ProfileController::class, 'getSettings'])->name('data');
            Route::post('/update', [\App\Http\Controllers\ProfileController::class, 'updateSettings'])->name('update');

            // Security Settings
            Route::get('/security', [\App\Http\Controllers\SecurityController::class, 'index'])->name('security.index');
            Route::post('/security/rules', [\App\Http\Controllers\SecurityController::class, 'storeRule'])->name('security.rules.store');
            Route::post('/security/rules/{rule}/toggle', [\App\Http\Controllers\SecurityController::class, 'toggleRule'])->name('security.rules.toggle');
            Route::delete('/security/rules/{rule}', [\App\Http\Controllers\SecurityController::class, 'deleteRule'])->name('security.rules.delete');
            Route::post('/security/mode', [\App\Http\Controllers\SecurityController::class, 'updateMode'])->name('security.mode.update');
            Route::post('/security/panel-domain', [\App\Http\Controllers\PanelDomainController::class, 'setup'])->name('security.panel-domain');
        });

        // Backups (Coming Soon)
        Route::get('/backups', function () {
            return \Inertia\Inertia::render('Backups/Index');
        })->name('backups.index');

        // FTP Accounts (Coming Soon)
        Route::get('/ftp', function () {
            return \Inertia\Inertia::render('FTP/Index');
        })->name('ftp.index');

        // Updates routes
        Route::prefix('updates')->name('updates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\UpdateController::class, 'index'])->name('index');
            Route::get('/check', [\App\Http\Controllers\UpdateController::class, 'checkForUpdates'])->name('check');
            Route::post('/perform', [\App\Http\Controllers\UpdateController::class, 'performUpdate'])->name('perform');
            Route::get('/status', [\App\Http\Controllers\UpdateController::class, 'getUpdateStatus'])->name('status');
            Route::post('/force-check', [\App\Http\Controllers\UpdateController::class, 'forceCheck'])->name('force-check');
        });

        // Nimbus Shield routes
        Route::prefix('shield')->name('shield.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ShieldController::class, 'index'])->name('index');
            Route::get('/status', [\App\Http\Controllers\ShieldController::class, 'getStatus'])->name('status');
            Route::post('/scan', [\App\Http\Controllers\ShieldController::class, 'startScan'])->name('scan');
            Route::post('/stop', [\App\Http\Controllers\ShieldController::class, 'stopScan'])->name('stop');
            Route::post('/quarantine', [\App\Http\Controllers\ShieldController::class, 'quarantine'])->name('quarantine');
            Route::post('/delete', [\App\Http\Controllers\ShieldController::class, 'deleteThreat'])->name('delete');
            
            // Firewall management
            Route::get('/firewall/rules', [\App\Http\Controllers\ShieldController::class, 'getFirewallRules'])->name('firewall.rules');
            Route::post('/firewall/add', [\App\Http\Controllers\ShieldController::class, 'addFirewallRule'])->name('firewall.add');
            Route::post('/firewall/delete', [\App\Http\Controllers\ShieldController::class, 'deleteFirewallRule'])->name('firewall.delete');
            Route::post('/firewall/toggle', [\App\Http\Controllers\ShieldController::class, 'toggleFirewall'])->name('firewall.toggle');
            Route::post('/install-tools', [\App\Http\Controllers\ShieldController::class, 'installTools'])->name('install-tools');
            Route::post('/settings', [\App\Http\Controllers\ShieldController::class, 'updateSettings'])->name('settings.update');
        });

        // User Management routes
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [\App\Http\Controllers\UserController::class, 'index'])->name('index');
            Route::get('/list', [\App\Http\Controllers\UserController::class, 'list'])->name('list');
            Route::get('/domains', [\App\Http\Controllers\UserController::class, 'availableDomains'])->name('domains');
            Route::post('/', [\App\Http\Controllers\UserController::class, 'store'])->name('store');
            Route::put('/{id}', [\App\Http\Controllers\UserController::class, 'update'])->name('update');
            Route::put('/{id}/websites', [\App\Http\Controllers\UserController::class, 'updateWebsites'])->name('websites');
            Route::delete('/{id}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('destroy');
        });
    });
});
