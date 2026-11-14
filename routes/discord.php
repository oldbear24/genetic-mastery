<?php

use App\Http\Controllers\DiscordInteractionController;
use App\Http\Middleware\VerifyDiscordSignature;
use Illuminate\Support\Facades\Route;

// Discord HTTP interactions. No session, cookies or CSRF: Discord signs
// every request with Ed25519 instead.
Route::post('/discord/interactions', DiscordInteractionController::class)
    ->middleware(['throttle:interactions', VerifyDiscordSignature::class])
    ->name('discord.interactions');
