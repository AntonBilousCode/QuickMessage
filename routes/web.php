<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\KeyController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', fn () => redirect()->route('login'));

// ─── Guest routes ───────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,5');

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:2000,1');
});

// ─── Authenticated routes ────────────────────────────────────
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    // Keys (E2EE)
    Route::post('/keys', [KeyController::class, 'store'])->name('keys.store')->middleware('throttle:60,1');
    Route::get('/keys/me', [KeyController::class, 'showOwn'])->name('keys.me')->middleware('throttle:120,1');
    Route::get('/keys/{user}', [KeyController::class, 'showPublic'])->name('keys.show')->middleware('throttle:120,1');

    // Settings
    Route::get('/settings', [UserSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/e2ee', [UserSettingsController::class, 'updateE2ee'])->name('settings.e2ee')->middleware('throttle:30,1');

    // Messages
    Route::get('/messages/unread', [MessageController::class, 'unread'])->name('messages.unread');
    Route::get('/messages/{user}', [MessageController::class, 'index'])->name('messages.show');
    Route::post('/messages/{user}', [MessageController::class, 'store'])->name('messages.store')->middleware('throttle:2000,1');
    Route::post('/messages/{user}/read', [MessageController::class, 'markRead'])->name('messages.read');
});
