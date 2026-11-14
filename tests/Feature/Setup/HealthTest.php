<?php

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('renders the home page in Czech', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('lang="cs"', false)
        ->assertSee('GENETIC MASTERY');
});
