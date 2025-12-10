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

// Protected routes
Route::middleware(['auth', \App\Http\Middleware\EnsureSetupComplete::class])->group(function () {
    
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/stats', [DashboardController::class, 'getStats'])->name('stats');
    });

    Route::get('/domains', function () {
        return Inertia::render('Domains/Index');
    })->name('domains.list');

    Route::prefix('domains')->group(function () {
        Route::get('/api', [DomainController::class, 'index'])->name('domain.index');
        Route::post('/', [DomainController::class, 'store'])->name('domain.store');
        Route::put('/{domain}', [DomainController::class, 'update'])->name('domain.update');
        Route::delete('/{domain}', [DomainController::class, 'destroy'])->name('domain.destroy');
    });

    // File Manager routes
    Route::prefix('file-manager')->name('file-manager.')->group(function () {
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
        Route::post('/{domain}/upload', [FileManagerController::class, 'upload'])->name('upload');
        Route::get('/{domain}/download', [FileManagerController::class, 'download'])->name('download');
    });

    // PHP Configuration routes
    Route::prefix('php')->name('php.')->group(function () {
        Route::get('/', [PhpController::class, 'index'])->name('index');
        Route::get('/info', [PhpController::class, 'getInfo'])->name('info');
        Route::post('/read', [PhpController::class, 'readIni'])->name('read');
        Route::post('/save', [PhpController::class, 'saveIni'])->name('save');
        Route::post('/update-setting', [PhpController::class, 'updateSetting'])->name('update-setting');
        Route::post('/restart', [PhpController::class, 'restartPhp'])->name('restart');
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

    // SSL Certificate routes
    Route::prefix('ssl')->name('ssl.')->group(function () {
        Route::get('/', [SslController::class, 'index'])->name('index');
        Route::get('/domains', [SslController::class, 'getDomains'])->name('domains');
        Route::post('/install', [SslController::class, 'installCertificate'])->name('install');
        Route::post('/renew', [SslController::class, 'renewCertificate'])->name('renew');
        Route::post('/renew-all', [SslController::class, 'renewAll'])->name('renew-all');
        Route::post('/remove', [SslController::class, 'removeCertificate'])->name('remove');
    });

    // Database Management routes
    Route::prefix('database')->name('database.')->group(function () {
        Route::get('/', [DatabaseController::class, 'index'])->name('index');
        Route::get('/status', [DatabaseController::class, 'getStatus'])->name('status');
        Route::post('/install-phpmyadmin', [DatabaseController::class, 'installPhpMyAdmin'])->name('install-phpmyadmin');
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
        Route::post('/phpmyadmin/access', [DatabaseController::class, 'getPhpMyAdminUrl'])->name('phpmyadmin.access');
        Route::get('/phpmyadmin/signon/{token}', [DatabaseController::class, 'phpMyAdminSignon'])->name('phpmyadmin.signon');
        Route::get('/phpmyadmin-view', [DatabaseController::class, 'phpMyAdminView'])->name('phpmyadmin.view');
    });
});