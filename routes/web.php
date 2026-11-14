<?php

use App\Http\Controllers\Auth\DiscordLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MilestoneController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', HomeController::class)->name('home');
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
Route::get('/milestones/{milestone:code}', [MilestoneController::class, 'show'])->name('milestones.show');

// Discord login
Route::middleware('guest')->group(function () {
    Route::get('/login', [DiscordLoginController::class, 'show'])->name('login');
    Route::middleware('throttle:login')->group(function () {
        Route::get('/auth/discord', [DiscordLoginController::class, 'redirect'])->name('auth.discord');
        Route::get('/auth/discord/callback', [DiscordLoginController::class, 'callback'])->name('auth.discord.callback');
    });
});
Route::post('/logout', [DiscordLoginController::class, 'logout'])->middleware('auth')->name('logout');

// Players
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
