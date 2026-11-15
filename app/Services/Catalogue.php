<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\Species;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The milestone catalogue: species (admin managed) × stats (config). Every
 * species has one milestone per stat; an admin can switch off a whole
 * species or single stats. Nothing is ever deleted, so claims and evidence
 * of switched off milestones stay and count again when switched back on.
 */
class Catalogue
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Creates missing milestones, updates codes when the required value in
     * config changes and keeps sort_order in catalogue order (species order,
     * then stat order). Never deletes and never changes is_active of an
     * existing milestone, so it is safe to run on every deploy.
     */
    public function sync(): void
    {
        $value = (int) config('genetic.required_value');
        $stats = array_keys(config('genetic.stats'));

        DB::transaction(function () use ($value, $stats) {
            $existing = Milestone::query()->without('species')->get()->keyBy(fn (Milestone $m) => "{$m->dinosaur}|{$m->stat}");
            $order = 0;

            foreach (Species::query()->ordered()->get() as $species) {
                foreach ($stats as $stat) {
                    $milestone = $existing->get("{$species->code}|{$stat}") ?? new Milestone([
                        'dinosaur' => $species->code,
                        'stat' => $stat,
                        'is_active' => true,
                    ]);

                    $milestone->fill([
                        'code' => Milestone::codeFor($species->code, $stat, $value),
                        'required_value' => $value,
                        'sort_order' => ++$order,
                    ])->save();
                }
            }
        });
    }

    /**
     * Adds a species with all stats switched on, at the end of the catalogue.
     *
     * @param  string|null  $image  storage key from SpeciesArt::store()
     */
    public function create(User $admin, string $code, string $name, ?string $image = null): Species
    {
        return DB::transaction(function () use ($admin, $code, $name, $image) {
            $species = Species::query()->create([
                'code' => $code,
                'name' => $name,
                'sort_order' => (int) Species::query()->max('sort_order') + 1,
                'is_active' => true,
                'image' => $image,
            ]);

            $this->sync();

            $this->audit->record($admin, 'species.created', $species, null, array_filter([
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'stats' => array_keys(config('genetic.stats')),
                'image' => $image,
            ], fn ($value) => $value !== null));

            return $species;
        });
    }

    /**
     * Renames a species, switches it on or off, sets which of its stats
     * count and replaces or removes its uploaded image. Only changed values
     * go to the audit log.
     *
     * @param  list<string>  $activeStats  stat codes that stay switched on
     * @param  string|null  $image  new storage key from SpeciesArt::store(), null keeps the current image
     * @param  bool  $removeImage  back to the bundled render (or none); ignored when $image is given
     */
    public function update(User $admin, Species $species, string $name, bool $active, array $activeStats, ?string $image = null, bool $removeImage = false): void
    {
        DB::transaction(function () use ($admin, $species, $name, $active, $activeStats, $image, $removeImage) {
            $species = Species::query()->whereKey($species->id)->lockForUpdate()->firstOrFail();
            $milestones = $species->milestones()->without('species')->get();

            $before = ['name' => $species->name, 'is_active' => $species->is_active, 'stats' => $this->activeStats($milestones), 'image' => $species->image];

            $species->update([
                'name' => $name,
                'is_active' => $active,
                'image' => $image ?? ($removeImage ? null : $species->image),
            ]);

            foreach ($milestones as $milestone) {
                $milestone->update(['is_active' => in_array($milestone->stat, $activeStats, true)]);
            }

            $after = ['name' => $species->name, 'is_active' => $species->is_active, 'stats' => $this->activeStats($milestones), 'image' => $species->image];
            $changed = array_keys(array_filter($after, fn ($value, string $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));

            if ($changed !== []) {
                $this->audit->record($admin, 'species.updated', $species,
                    array_intersect_key($before, array_flip($changed)),
                    array_intersect_key($after, array_flip($changed)),
                );
            }
        });
    }

    /** Moves a species one place up or down in the catalogue. */
    public function move(User $admin, Species $species, string $direction): void
    {
        DB::transaction(function () use ($admin, $species, $direction) {
            $ordered = Species::query()->ordered()->lockForUpdate()->get()->values()->all();
            $index = collect($ordered)->search(fn (Species $s) => $s->is($species));

            if ($index === false) {
                return;
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if (! isset($ordered[$target])) {
                return;
            }

            $from = $ordered[$index]->sort_order;
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

            foreach ($ordered as $i => $row) {
                $row->update(['sort_order' => $i + 1]);
            }

            $this->sync();

            $moved = $ordered[$target];
            $this->audit->record($admin, 'species.moved', $moved, ['sort_order' => $from], ['sort_order' => $moved->sort_order]);
        });
    }

    /**
     * @param  Collection<int, Milestone>  $milestones
     * @return list<string> active stat codes in config order
     */
    private function activeStats(Collection $milestones): array
    {
        $active = $milestones->where('is_active', true)->pluck('stat')->all();

        return array_values(array_filter(array_keys(config('genetic.stats')), fn (string $stat) => in_array($stat, $active, true)));
    }
}
