<?php

namespace App\Http\Controllers;

use App\Services\EnvironmentCheck;
use App\Services\Evidence\EvidenceProcessor;
use App\Services\Evidence\InvalidEvidence;
use App\Services\Maintenance;
use Database\Seeders\MilestoneSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operations over HTTP for hosts without SSH and with a URL-only cron
 * (WEDOS webhosting). Protected by long random tokens from .env; with no
 * token configured the endpoints do not exist (404). Responses are plain
 * text and never contain secret values.
 */
class OpsController extends Controller
{
    /** /ops/cron/{token}: one maintenance run (outbox, backup when due). */
    public function cron(string $token, Maintenance $maintenance): Response
    {
        $this->authorizeToken('cron_token', $token);

        $report = $maintenance->tick();

        return $this->text(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** /ops/deploy/{action}/{token}: deployment steps that normally need a shell. */
    public function deploy(Request $request, string $action, string $token, EnvironmentCheck $check): Response
    {
        $this->authorizeToken('deploy_token', $token);
        Log::info('Ops deploy action.', ['action' => $action, 'ip' => $request->ip()]);

        return match ($action) {
            'check' => $this->check($check, $token),
            'migrate' => $this->migrate(),
            'optimize' => $this->artisan([['optimize', []]]),
            'clear' => $this->artisan([['optimize:clear', []]]),
            'discord-commands' => $this->artisan([['discord:register-commands', []]]),
            default => abort(404),
        };
    }

    /**
     * POST /ops/deploy/upload-test/{token}: proves that uploaded files can
     * be read and processed on this host (open_basedir, GD).
     */
    public function uploadTest(Request $request, string $token, EvidenceProcessor $evidence): Response
    {
        $this->authorizeToken('deploy_token', $token);

        $file = $request->file('image');
        if (! $file instanceof UploadedFile) {
            return $this->text("FAIL no file received (upload_max_filesize / post_max_size?)\n", 422);
        }

        try {
            $info = $evidence->inspect($file);
        } catch (InvalidEvidence $e) {
            return $this->text('FAIL '.$e->translated()."\n", 422);
        }

        return $this->text(sprintf("OK upload readable, %s %d×%d, %d bytes\n", $info['mime'], $info['width'], $info['height'], $info['bytes']));
    }

    /** Migrations and the milestone seed; creates an empty SQLite file on the first run. */
    private function migrate(): Response
    {
        $config = DB::connection()->getConfig();

        if (DB::connection()->getDriverName() === 'sqlite' && $config['database'] !== ':memory:' && ! file_exists($config['database'])) {
            File::ensureDirectoryExists(dirname($config['database']));
            touch($config['database']);
            DB::purge();
        }

        return $this->artisan([
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => MilestoneSeeder::class, '--force' => true]],
        ]);
    }

    private function check(EnvironmentCheck $check, string $token): Response
    {
        $results = $check->run();
        $lines = array_map(fn (array $r) => sprintf('%-4s  %-34s %s', strtoupper($r['status']), $r['check'], $r['detail']), $results);
        $summary = $check->passed($results) ? 'RESULT: OK' : 'RESULT: FAIL (fix the FAIL lines)';

        $body = implode("\n", $lines)."\n\n{$summary}\n";
        $body .= "\nUpload test: POST an image as field \"image\" to ".url("/ops/deploy/upload-test/{$token}")."\n";

        return $this->text($body, $check->passed($results) ? 200 : 500);
    }

    /** @param list<array{0: string, 1: array<string, mixed>}> $commands */
    private function artisan(array $commands): Response
    {
        $output = '';
        $failed = false;

        foreach ($commands as [$command, $arguments]) {
            try {
                $code = Artisan::call($command, $arguments);
                $output .= "$ php artisan {$command}\n".Artisan::output()."\n";
                $failed = $failed || $code !== 0;
            } catch (Throwable $e) {
                $output .= "$ php artisan {$command}\nFAIL ".$e::class.': '.$e->getMessage()."\n";
                $failed = true;
                break;
            }
        }

        return $this->text($output, $failed ? 500 : 200);
    }

    private function authorizeToken(string $key, string $token): void
    {
        $expected = (string) config("genetic.ops.{$key}");

        if (strlen($expected) < (int) config('genetic.ops.min_token_length') || ! hash_equals($expected, $token)) {
            abort(404);
        }
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
