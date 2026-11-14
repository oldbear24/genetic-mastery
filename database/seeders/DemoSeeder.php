<?php

namespace Database\Seeders;

use App\Enums\SubmissionStatus;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample data matching design/screens (23 of 54 verified) for local
 * development and screenshots. Never run in production:
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder does not run in production.');

            return;
        }

        $this->call(MilestoneSeeder::class);

        $players = collect(['Joe', 'Kira', 'Eva', 'Marek', 'Ondra'])->mapWithKeys(fn (string $name, int $i) => [
            $name => User::query()->updateOrCreate(
                ['discord_id' => (string) (100000000000000100 + $i)],
                ['username' => mb_strtolower($name), 'global_name' => $name, 'display_name' => $name, 'is_admin' => false, 'is_member' => true, 'roles_checked_at' => now()],
            ),
        ]);
        $admins = collect(['Kuba', 'Lenka'])->mapWithKeys(fn (string $name, int $i) => [
            $name => User::query()->updateOrCreate(
                ['discord_id' => (string) (100000000000000200 + $i)],
                ['username' => mb_strtolower($name), 'global_name' => $name, 'display_name' => $name, 'is_admin' => true, 'is_member' => true, 'roles_checked_at' => now()],
            ),
        ]);

        // V:name = verified, P:name = pending, L = locked (same layout as the design).
        $grid = [
            'REX' => ['V:Joe', 'V:Kira', 'L', 'V:Joe', 'V:Marek', 'P:Kira'],
            'RAPTOR' => ['V:Eva', 'V:Eva', 'V:Ondra', 'L', 'V:Kira', 'V:Joe'],
            'CARNO' => ['V:Marek', 'L', 'L', 'V:Kira', 'V:Marek', 'L'],
            'GIGA' => ['V:Ondra', 'L', 'L', 'L', 'V:Kira', 'V:Joe'],
            'THERI' => ['V:Eva', 'V:Ondra', 'L', 'L', 'P:Marek', 'L'],
            'SPINO' => ['L', 'V:Marek', 'L', 'V:Eva', 'L', 'L'],
            'ALLO' => ['V:Kira', 'L', 'P:Ondra', 'L', 'V:Joe', 'L'],
            'YUTY' => ['L', 'L', 'L', 'V:Ondra', 'L', 'P:Eva'],
            'MEGALO' => ['L', 'V:Eva', 'L', 'L', 'L', 'L'],
        ];

        Submission::query()->delete();

        $stats = array_keys(config('genetic.stats'));
        $hoursAgo = 2;

        foreach ($grid as $dinosaur => $cells) {
            foreach ($cells as $i => $cell) {
                if ($cell === 'L') {
                    continue;
                }

                [$state, $name] = explode(':', $cell);
                $milestone = Milestone::query()->where('dinosaur', $dinosaur)->where('stat', $stats[$i])->firstOrFail();
                $player = $players[$name];
                $submittedAt = now()->subHours($hoursAgo + 1);

                Submission::query()->create([
                    'milestone_id' => $milestone->id,
                    'user_id' => $player->id,
                    'player_name_snapshot' => $player->display_name,
                    'status' => $state === 'V' ? SubmissionStatus::Verified : SubmissionStatus::Pending,
                    'submitted_at' => $submittedAt,
                    'reviewed_by' => $state === 'V' ? $admins->random()->id : null,
                    'reviewed_at' => $state === 'V' ? now()->subHours($hoursAgo) : null,
                ]);

                $hoursAgo += 9;
            }
        }
    }
}
