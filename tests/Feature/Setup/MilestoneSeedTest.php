<?php

use App\Models\Milestone;
use App\Models\Species;
use Database\Seeders\MilestoneSeeder;
use Illuminate\Support\Facades\DB;

it('seeds 60 milestones with unique codes, 10 species by 6 stats, all requiring 250', function () {
    expect(Milestone::count())->toBe(60)
        ->and(Milestone::query()->distinct()->count('code'))->toBe(60)
        ->and(Milestone::query()->distinct()->count('dinosaur'))->toBe(10)
        ->and(Milestone::query()->distinct()->count('stat'))->toBe(6)
        ->and(Milestone::query()->where('required_value', '!=', 250)->count())->toBe(0)
        ->and(Milestone::query()->active()->count())->toBe(60);

    foreach (Milestone::all() as $milestone) {
        expect($milestone->code)->toBe("{$milestone->dinosaur}_{$milestone->stat}_250");
    }
});

it('starts with the species catalogue from the config, all active', function () {
    expect(Species::query()->ordered()->pluck('name', 'code')->all())->toBe(config('genetic.species'))
        ->and(Species::query()->where('is_active', false)->count())->toBe(0)
        ->and(Milestone::query()->distinct()->pluck('stat')->all())
        ->toEqualCanonicalizing(['HEALTH', 'STAMINA', 'OXYGEN', 'FOOD', 'WEIGHT', 'MELEE']);

    expect(Milestone::where('code', 'GIGA_MELEE_250')->first())
        ->not->toBeNull()
        ->title()->toBe('Giganotosaurus · Melee Damage');
});

it('orders milestones by species, then stat', function () {
    $codes = Milestone::orderBy('sort_order')->pluck('code');

    expect($codes->first())->toBe('REX_HEALTH_250')
        ->and($codes->get(5))->toBe('REX_MELEE_250')
        ->and($codes->get(6))->toBe('RAPTOR_HEALTH_250')
        ->and($codes->get(12))->toBe('GIGA_HEALTH_250')
        ->and($codes->last())->toBe('BASILISK_MELEE_250');
});

it('is idempotent and keeps existing rows and their on/off switches', function () {
    Milestone::query()->where('code', 'TUSO_OXYGEN_250')->update(['is_active' => false]);
    $idsBefore = Milestone::orderBy('id')->pluck('id')->all();

    $this->seed(MilestoneSeeder::class);

    expect(Milestone::count())->toBe(60)
        ->and(Milestone::orderBy('id')->pluck('id')->all())->toBe($idsBefore)
        ->and(Milestone::query()->active()->count())->toBe(59);
});

it('takes the required value from the config', function () {
    config(['genetic.required_value' => 300]);

    $this->seed(MilestoneSeeder::class);

    expect(Milestone::count())->toBe(60)
        ->and(Milestone::query()->without('species')->where('code', 'REX_HEALTH_300')->value('required_value'))->toBe(300);
});

it('keeps species of the first catalogue as inactive when the species table is created', function () {
    if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('DDL commits the test transaction on MySQL and MariaDB.');
    }

    $migration = require database_path('migrations/2026_11_15_000100_create_species_table.php');
    $migration->down();

    // A milestone of a species that was in the first catalogue but is no longer in config.
    DB::table('milestones')->insert([
        'code' => 'SPINO_HEALTH_250', 'dinosaur' => 'SPINO', 'stat' => 'HEALTH',
        'required_value' => 250, 'sort_order' => 999, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();

    expect(Species::query()->where('code', 'SPINO')->first())
        ->name->toBe('Spinosaurus')
        ->is_active->toBeFalse()
        ->and(Species::query()->where('code', 'REX')->first()->is_active)->toBeTrue()
        ->and(Milestone::query()->active()->count())->toBe(60)
        ->and(Milestone::query()->count())->toBe(61);
});
