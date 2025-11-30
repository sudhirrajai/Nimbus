<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;

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
