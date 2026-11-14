<?php

use App\Http\Controllers\Auth\DiscordLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\SubmissionController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', HomeController::class)->name('home');
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
Route::get('/milestones/{milestone:code}', [MilestoneController::class, 'show'])->name('milestones.show');

// Submission detail and evidence: public for verified submissions, otherwise
// author and admins only (policies).
Route::get('/submissions/{submission:public_id}', [SubmissionController::class, 'show'])
    ->where('submission', 'S-[0-9]+')
    ->name('submissions.show');
Route::get('/evidence/{image:storage_key}/{variant}', [EvidenceController::class, 'show'])
    ->whereUuid('image')
    ->whereIn('variant', ['display', 'thumb', 'original'])
    ->name('evidence.show');

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
    Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/new', [SubmissionController::class, 'create'])->name('submissions.create');
    Route::post('/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::post('/submissions/{submission:public_id}/withdraw', [SubmissionController::class, 'withdraw'])->name('submissions.withdraw');
});

// Local development only: signed login links from `php artisan dev:login`.
if (app()->isLocal()) {
    Route::get('/dev/login/{user}', function (User $user) {
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('dashboard');
    })->middleware('signed')->name('dev.login');
}
