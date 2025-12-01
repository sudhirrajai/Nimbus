<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\FileManagerController;

Route::prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/stats', [DashboardController::class, 'getStats'])->name('stats');
});

// Changed from /domains-list to /domains to match the navigation
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
    Route::post('/{domain}/rename', [FileManagerController::class, 'rename'])->name('rename');
    Route::post('/{domain}/upload', [FileManagerController::class, 'upload'])->name('upload');
    Route::get('/{domain}/download', [FileManagerController::class, 'download'])->name('download');
});