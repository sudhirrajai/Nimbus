<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\PhpController;
use App\Http\Controllers\NginxController;

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
    // Main file manager view
    Route::get('/{domain}', [FileManagerController::class, 'index'])->name('index');
    
    // File operations
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