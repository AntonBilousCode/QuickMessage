<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
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

    // Messages
    Route::get('/messages/unread', [MessageController::class, 'unread'])->name('messages.unread');
    Route::get('/messages/{user}', [MessageController::class, 'index'])->name('messages.show');
    Route::post('/messages/{user}', [MessageController::class, 'store'])->name('messages.store')->middleware('throttle:2000,1');
    Route::post('/messages/{user}/read', [MessageController::class, 'markRead'])->name('messages.read');
});
