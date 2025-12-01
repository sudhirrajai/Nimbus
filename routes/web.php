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

Route::get('/domains-list', function () {
    return Inertia::render('Domains/Index');
})->name('domains.list');

Route::prefix('domains')->group(function () {
    Route::get('/', [DomainController::class, 'index'])->name('domain.index');
    Route::post('/', [DomainController::class, 'store'])->name('domain.store');
    Route::put('/{domain}', [DomainController::class, 'update'])->name('domain.update');
    Route::delete('/{domain}', [DomainController::class, 'destroy'])->name('domain.destroy');
});

// File Manager routes
Route::prefix('file-manager/{domain}')->group(function () {
    // Main file manager view
    Route::get('/', [FileManagerController::class, 'index'])->name('file-manager.index');
    
    // File operations
    Route::post('/list', [FileManagerController::class, 'list'])->name('file-manager.list');
    Route::post('/read', [FileManagerController::class, 'read'])->name('file-manager.read');
    Route::post('/save', [FileManagerController::class, 'save'])->name('file-manager.save');
    Route::post('/create-file', [FileManagerController::class, 'createFile'])->name('file-manager.create-file');
    Route::post('/create-directory', [FileManagerController::class, 'createDirectory'])->name('file-manager.create-directory');
    Route::post('/delete', [FileManagerController::class, 'delete'])->name('file-manager.delete');
    Route::post('/rename', [FileManagerController::class, 'rename'])->name('file-manager.rename');
    Route::post('/upload', [FileManagerController::class, 'upload'])->name('file-manager.upload');
    Route::get('/download', [FileManagerController::class, 'download'])->name('file-manager.download');
});