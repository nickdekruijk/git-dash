<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\ShareController;
use App\Http\Middleware\RequiresDashboardPassword;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

// Protected dashboard (requires password)
Route::middleware(RequiresDashboardPassword::class)->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/share-links', Dashboard::class)->name('share-links');
    Route::get('/connections', Dashboard::class)->name('connections');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

// Login
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);

// Public share links — locked to a specific connection
Route::get('/s/{token}', [ShareController::class, 'show'])->name('share');
