<?php

use Illuminate\Support\Facades\Route;

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('renders the home page in Czech', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('lang="cs"', false)
        ->assertSee('GENETIC MASTERY');
});

it('has no development login outside APP_ENV=local', function () {
    expect(app()->isLocal())->toBeFalse()
        ->and(Route::has('dev.login'))->toBeFalse();

    $this->get('/dev/login/1')->assertNotFound();
    $this->artisan('dev:login', ['user' => 'anyone'])->assertFailed();
});
