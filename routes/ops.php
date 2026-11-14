<?php

use App\Http\Controllers\OpsController;
use App\Http\Middleware\RelocateUnreadableUploads;
use Illuminate\Support\Facades\Route;

// Operations for hosts without SSH and with a URL-only cron (WEDOS
// webhosting). No session or CSRF; long tokens from .env, 404 without them.
Route::middleware('throttle:ops')->prefix('ops')->group(function () {
    Route::get('/cron/{token}', [OpsController::class, 'cron'])->name('ops.cron');
    Route::post('/deploy/upload-test/{token}', [OpsController::class, 'uploadTest'])
        ->middleware(RelocateUnreadableUploads::class)
        ->name('ops.upload-test');
    Route::get('/deploy/{action}/{token}', [OpsController::class, 'deploy'])
        ->whereIn('action', ['check', 'migrate', 'optimize', 'clear', 'discord-commands'])
        ->name('ops.deploy');
});
