<?php

/**
 * Helper for the concurrent approval test. Runs in separate PHP processes
 * against a file based SQLite database (DB_DATABASE from the environment).
 *
 *   php approval-race.php setup
 *   php approval-race.php approve <submissionId> <adminId> <startAtMicrotime>
 *   php approval-race.php state
 */

use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\DiscordOutbox;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\User;
use App\Services\ClusterProgress;
use App\Services\DecisionRefused;
use App\Services\ReviewService;
use Database\Seeders\MilestoneSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$command = $argv[1] ?? '';

switch ($command) {
    case 'setup':
        Artisan::call('migrate:fresh', ['--force' => true]);
        (new MilestoneSeeder)->run();
        $milestone = Milestone::query()->where('code', 'REX_MELEE_250')->firstOrFail();
        $adminA = User::factory()->admin()->create(['display_name' => 'Kuba']);
        $adminB = User::factory()->admin()->create(['display_name' => 'Lenka']);
        $claimA = Submission::factory()->create(['milestone_id' => $milestone->id, 'submitted_at' => now()->subHour()]);
        $claimB = Submission::factory()->create(['milestone_id' => $milestone->id, 'submitted_at' => now()->subMinutes(30)]);
        echo json_encode(['adminA' => $adminA->id, 'adminB' => $adminB->id, 'claimA' => $claimA->id, 'claimB' => $claimB->id]);
        break;

    case 'approve':
        [, , $submissionId, $adminId, $startAt] = $argv;
        $admin = User::query()->findOrFail((int) $adminId);
        $submission = Submission::query()->findOrFail((int) $submissionId);
        // Both processes have loaded their (still pending) claim; now go at once.
        while (microtime(true) < (float) $startAt) {
            usleep(1000);
        }
        try {
            app(ReviewService::class)->approve($admin, $submission, null, true);
            echo 'ok';
        } catch (DecisionRefused $e) {
            echo 'refused: '.$e->getMessage();
        } catch (Throwable $e) {
            echo 'error: '.$e::class.' '.$e->getMessage();
        }
        break;

    case 'state':
        echo json_encode([
            'verified' => Submission::query()->verified()->count(),
            'superseded' => Submission::query()->where('status', SubmissionStatus::Superseded->value)->count(),
            'pending' => Submission::query()->pending()->count(),
            'progress' => app(ClusterProgress::class)->verifiedCount(),
            'outbox' => DiscordOutbox::query()->count(),
            'approved_audit' => AuditLog::query()->where('action', 'submission.approved')->count(),
        ]);
        break;

    default:
        fwrite(STDERR, "Unknown command\n");
        exit(1);
}
