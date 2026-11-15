<?php

use App\Models\Submission;
use App\Support\SpeciesImage;

it('is public and shows the 9 × 6 grid in Czech', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSee('lang="cs"', false)
        ->assertSee('Milníky')
        ->assertSee('9 druhů × 6 statů · každý potřebuje 250 bodů')
        ->assertSee('Přihlásit přes Discord');

    foreach (config('genetic.species') as $name) {
        $response->assertSee($name);
    }
    foreach (config('genetic.stats') as $name) {
        $response->assertSee($name);
    }
});

it('shows a lock, an hourglass or the discoverer for each cell', function () {
    Submission::factory()->for(player('Joe'))->onMilestone('GIGA_MELEE_250')->verified()->create();
    Submission::factory()->for(player('Kira'))->onMilestone('REX_MELEE_250')->create();

    $this->get('/')
        ->assertSee('Giganotosaurus · Melee Damage: ověřeno, objevitel Joe')
        ->assertSee('Rex · Melee Damage: čeká na schválení')
        ->assertSee('Rex · Health: zamčeno')
        ->assertSee('OVĚŘENO')
        ->assertSee('ČEKÁ');
});

it('does not reveal who has a pending claim (HP-4)', function () {
    Submission::factory()->for(player('SecretKira'))->onMilestone('REX_MELEE_250')->create();

    $this->get('/')->assertDontSee('SecretKira');
    $this->get(route('milestones.show', 'REX_MELEE_250'))->assertOk()->assertDontSee('SecretKira');
});

it('links every cell to the milestone detail (HP-3)', function () {
    $this->get('/')->assertSee(route('milestones.show', 'GIGA_MELEE_250'), false);
});

it('lists the last 5 verified milestones, newest first (HP-5)', function () {
    $codes = ['REX_HEALTH_250', 'REX_STAMINA_250', 'REX_OXYGEN_250', 'REX_FOOD_250', 'REX_WEIGHT_250', 'REX_MELEE_250'];
    foreach ($codes as $i => $code) {
        Submission::factory()->for(player("P{$i}x"))->onMilestone($code)->verified()->create(['reviewed_at' => now()->subHours(10 - $i)]);
    }

    $response = $this->get('/');

    $response->assertSeeInOrder(['Poslední objevy', 'Rex · Melee Damage', 'Objevitel: P5x', 'Rex · Weight', 'Objevitel: P4x'])
        ->assertSee('Celý žebříček')
        ->assertSee(route('leaderboard'), false);

    // The oldest of the six is not among the recent five.
    $recentSection = str($response->getContent())->between('Poslední objevy', 'Nejlepší objevitelé');
    expect((string) $recentSection)->not->toContain('Objevitel: P0x');
});

it('formats the percentage the Czech way', function () {
    verifyMilestones(23);

    $this->get('/')->assertSee('ověřených milníků v celém clusteru · 42,6 %');
});

it('renders the milestone detail of a verified milestone publicly', function () {
    Submission::factory()->for(player('Joe'))->onMilestone('GIGA_MELEE_250')->verified(admin('Kuba'))->create();

    $this->get(route('milestones.show', 'GIGA_MELEE_250'))
        ->assertOk()
        ->assertSee('GIGA_MELEE_250 · požadovaná hodnota 250')
        ->assertSee('OVĚŘENO – objevitel Joe')
        ->assertSee('Ověřující admin')
        ->assertSee('Kuba');
});

it('returns 404 for an unknown milestone code', function () {
    $this->get('/milestones/NOPE_HEALTH_250')->assertNotFound()->assertSee('Stránka nenalezena');
});

it('has a species image for every configured species', function () {
    foreach (array_keys(config('genetic.species')) as $code) {
        expect(SpeciesImage::url($code))->not->toBeNull('Missing public/images/species/'.strtolower($code).'.webp');
    }
});

it('shows species images in the grid and behind the counters', function () {
    $response = $this->get('/')->assertOk();

    foreach (array_keys(config('genetic.species')) as $code) {
        $response->assertSee(SpeciesImage::url($code), false);
    }

    foreach (['genetic-mastery', 'indo-potential'] as $image) {
        $response->assertSee(asset("images/{$image}.webp"), false);
        expect(public_path("images/{$image}.webp"))->toBeFile();
    }
});

it('renders a species without an image file without the image', function () {
    expect(SpeciesImage::url('NOPE'))->toBeNull();

    $this->blade('<x-species-art code="NOPE" />')->assertDontSee('<img', false);
});
