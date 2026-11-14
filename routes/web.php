<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CorrectionController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SubmissionListController;
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

// Admins (SEC-2: every action is checked on the server, non-admins get 403)
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [ReviewController::class, 'queue'])->name('queue');
    Route::get('/submissions/{submission:public_id}', [ReviewController::class, 'show'])->name('submissions.show');
    Route::post('/submissions/{submission:public_id}/approve', [ReviewController::class, 'approve'])->name('submissions.approve');
    Route::post('/submissions/{submission:public_id}/reject', [ReviewController::class, 'reject'])->name('submissions.reject');

    Route::get('/submissions', SubmissionListController::class)->name('submissions');
    Route::post('/submissions/{submission:public_id}/revoke', [CorrectionController::class, 'revoke'])->name('submissions.revoke');
    Route::post('/submissions/{submission:public_id}/reopen', [CorrectionController::class, 'reopen'])->name('submissions.reopen');
    Route::post('/submissions/{submission:public_id}/verifier', [CorrectionController::class, 'changeVerifier'])->name('submissions.verifier');
    Route::post('/submissions/{submission:public_id}/notes', [CorrectionController::class, 'addNote'])->name('submissions.notes');
    Route::get('/audit', AuditLogController::class)->name('audit');
});

// Local development only: signed login links from `php artisan dev:login`.
if (app()->isLocal()) {
    Route::get('/dev/login/{user}', function (User $user) {
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('dashboard');
    })->middleware('signed')->name('dev.login');
}
