<?php

use App\Models\Milestone;
use Database\Seeders\MilestoneSeeder;

it('seeds exactly 54 milestones with unique codes, 9 species by 6 stats, all requiring 250', function () {
    expect(Milestone::count())->toBe(54)
        ->and(Milestone::query()->distinct()->count('code'))->toBe(54)
        ->and(Milestone::query()->distinct()->count('dinosaur'))->toBe(9)
        ->and(Milestone::query()->distinct()->count('stat'))->toBe(6)
        ->and(Milestone::query()->where('required_value', '!=', 250)->count())->toBe(0);

    foreach (Milestone::all() as $milestone) {
        expect($milestone->code)->toBe("{$milestone->dinosaur}_{$milestone->stat}_250");
    }
});

it('uses the species and stat codes from the brief', function () {
    expect(Milestone::query()->distinct()->orderBy('dinosaur')->pluck('dinosaur')->all())
        ->toEqualCanonicalizing(['REX', 'RAPTOR', 'CARNO', 'GIGA', 'THERI', 'SPINO', 'ALLO', 'YUTY', 'MEGALO'])
        ->and(Milestone::query()->distinct()->pluck('stat')->all())
        ->toEqualCanonicalizing(['HEALTH', 'STAMINA', 'OXYGEN', 'FOOD', 'WEIGHT', 'MELEE']);

    expect(Milestone::where('code', 'GIGA_MELEE_250')->first())
        ->not->toBeNull()
        ->title()->toBe('Giganotosaurus · Melee Damage');
});

it('orders milestones by species, then stat, as listed in the config', function () {
    $codes = Milestone::orderBy('sort_order')->pluck('code');

    expect($codes->first())->toBe('REX_HEALTH_250')
        ->and($codes->get(5))->toBe('REX_MELEE_250')
        ->and($codes->get(6))->toBe('RAPTOR_HEALTH_250')
        ->and($codes->last())->toBe('MEGALO_MELEE_250');
});

it('is idempotent and keeps existing rows', function () {
    $idsBefore = Milestone::orderBy('id')->pluck('id')->all();

    $this->seed(MilestoneSeeder::class);

    expect(Milestone::count())->toBe(54)
        ->and(Milestone::orderBy('id')->pluck('id')->all())->toBe($idsBefore);
});

it('takes the required value from the config', function () {
    config(['genetic.required_value' => 300]);

    $this->seed(MilestoneSeeder::class);

    expect(Milestone::count())->toBe(54)
        ->and(Milestone::where('code', 'REX_HEALTH_300')->value('required_value'))->toBe(300);
});
