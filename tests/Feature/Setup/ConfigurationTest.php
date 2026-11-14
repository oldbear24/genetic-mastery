<?php

use Illuminate\Support\Facades\DB;

it('runs in Czech', function () {
    expect(app()->getLocale())->toBe('cs')
        ->and(config('app.fallback_locale'))->toBe('cs')
        ->and(__('validation.required', ['attribute' => 'důvod']))->toBe('Pole důvod je povinné.');
});

it('stores times in UTC', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

it('has the rules from the brief as defaults', function () {
    expect(config('genetic.indo.base'))->toBe(150)
        ->and(config('genetic.indo.per_milestone'))->toBe(25)
        ->and(config('genetic.upload.max_mb'))->toBe(15)
        ->and(config('genetic.upload.max_files'))->toBe(10)
        ->and(config('genetic.upload.max_total_mb'))->toBe(60)
        ->and(config('genetic.upload.max_megapixels'))->toBe(40)
        ->and(config('genetic.allow_self_approve'))->toBeFalse()
        ->and(config('genetic.species'))->toHaveCount(9)
        ->and(config('genetic.stats'))->toHaveCount(6);
});

it('starts SQLite write transactions immediately so concurrent approvals wait for the lock', function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('SQLite only.');
    }

    expect(config('database.connections.sqlite.transaction_mode'))->toBe('IMMEDIATE')
        ->and(config('database.connections.sqlite.journal_mode'))->toBe('wal');
});

it('keeps the session cookie for 7 days, HttpOnly and SameSite=Lax', function () {
    expect(config('session.lifetime'))->toBe(10080)
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});
