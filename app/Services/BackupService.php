<?php

namespace App\Services;

use App\Models\SubmissionImage;
use App\Services\Evidence\EvidenceProcessor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Daily backups (kap. Zálohy): a consistent database snapshot (SQLite
 * VACUUM INTO, PostgreSQL pg_dump) plus a mirror of the evidence files,
 * kept for 30 days on the backup disk. Evidence files never change and are
 * never deleted (UPL-9), so the mirror only copies new files.
 *
 * For a copy outside the server, point BACKUP_DISK at an off-site disk
 * (S3 compatible storage or a mounted volume).
 */
class BackupService
{
    public function __construct(private readonly EvidenceProcessor $evidence) {}

    /** @return array{snapshot: string, evidence_copied: int, pruned: int} */
    public function run(): array
    {
        $stamp = now()->utc()->format('Ymd-His');
        $snapshot = $this->snapshotDatabase($stamp);
        $copied = $this->mirrorEvidence();
        $pruned = $this->prune();

        return ['snapshot' => $snapshot, 'evidence_copied' => $copied, 'pruned' => $pruned];
    }

    /**
     * Restore test: unpack the snapshot into a temporary SQLite file, check
     * its integrity and that every evidence original it references exists
     * in the mirror.
     *
     * @return array{snapshot: string, ok: bool, integrity: string, milestones: int, submissions: int, images: int, missing_files: list<string>}
     */
    public function verify(?string $snapshot = null): array
    {
        $snapshot ??= $this->latestSnapshot();

        if ($snapshot === null) {
            throw new RuntimeException('No database snapshot found.');
        }

        if (! str_ends_with($snapshot, '.sqlite.gz')) {
            throw new RuntimeException('Only SQLite snapshots can be verified automatically. Restore a pg_dump with pg_restore into a scratch database.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gm-restore');
        $this->gunzipFromDisk($snapshot, $tmp);

        try {
            $pdo = new PDO('sqlite:'.$tmp);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $count = fn (string $table) => (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

            $missing = [];
            $images = $pdo->query('SELECT storage_key, mime FROM submission_images')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($images as $image) {
                $path = $this->mirrorPath(SubmissionImage::pathFor($image['storage_key'], 'original', $image['mime']));
                if (! $this->disk()->exists($path)) {
                    $missing[] = $path;
                }
            }

            $result = [
                'snapshot' => $snapshot,
                'integrity' => $integrity,
                'milestones' => $count('milestones'),
                'submissions' => $count('submissions'),
                'images' => count($images),
                'missing_files' => $missing,
            ];
            $pdo = null;
        } finally {
            @unlink($tmp);
        }

        $result['ok'] = $result['integrity'] === 'ok' && $result['missing_files'] === [] && $result['milestones'] > 0;

        return $result;
    }

    /** @return list<string> snapshot paths, newest first */
    public function snapshots(): array
    {
        return collect($this->disk()->files($this->base('db')))
            ->filter(fn (string $path) => preg_match('/genetic-\d{8}-\d{6}\.(sqlite\.gz|pgdump)$/', $path))
            ->sortDesc()
            ->values()
            ->all();
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('genetic.backup.disk'));
    }

    private function latestSnapshot(): ?string
    {
        return $this->snapshots()[0] ?? null;
    }

    private function snapshotDatabase(string $stamp): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gm-backup');
        @unlink($tmp); // VACUUM INTO needs a path that does not exist yet

        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                // A consistent copy even while the app writes (WAL).
                DB::statement('VACUUM INTO ?', [$tmp]);
                $target = $this->base("db/genetic-{$stamp}.sqlite.gz");
                $this->gzipToDisk($tmp, $target);
            } elseif ($driver === 'pgsql') {
                $this->pgDump($tmp);
                $target = $this->base("db/genetic-{$stamp}.pgdump");
                $stream = fopen($tmp, 'rb');
                $this->disk()->writeStream($target, $stream);
                fclose($stream);
            } else {
                throw new RuntimeException("Backups are not implemented for the {$driver} driver.");
            }
        } finally {
            @unlink($tmp);
        }

        return $target;
    }

    private function pgDump(string $file): void
    {
        $config = DB::connection()->getConfig();
        $process = new Process(
            ['pg_dump', '--format=custom', '--no-owner', '--file='.$file,
                '--host='.$config['host'], '--port='.$config['port'], '--username='.$config['username'], $config['database']],
            null,
            ['PGPASSWORD' => (string) ($config['password'] ?? '')],
        );
        $process->setTimeout(600);
        $process->mustRun();
    }

    /** Copy evidence files that are not in the mirror yet. */
    private function mirrorEvidence(): int
    {
        $source = $this->evidence->disk();
        $target = $this->disk();
        $copied = 0;

        foreach ($source->allFiles('evidence') as $path) {
            $mirror = $this->mirrorPath($path);

            if ($target->exists($mirror)) {
                continue;
            }

            $stream = $source->readStream($path);
            $target->writeStream($mirror, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $copied++;
        }

        return $copied;
    }

    /** Delete database snapshots older than the retention period. */
    private function prune(): int
    {
        $cutoff = now()->utc()->subDays((int) config('genetic.backup.retention_days'));
        $pruned = 0;

        foreach ($this->snapshots() as $path) {
            if (! preg_match('/genetic-(\d{8}-\d{6})\./', $path, $m)) {
                continue;
            }

            if (Carbon::createFromFormat('Ymd-His', $m[1], 'UTC')->lt($cutoff)) {
                $this->disk()->delete($path);
                $pruned++;
            }
        }

        return $pruned;
    }

    private function gzipToDisk(string $file, string $target): void
    {
        $gz = tempnam(sys_get_temp_dir(), 'gm-gz');
        $in = fopen($file, 'rb');
        $out = gzopen($gz, 'wb6');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);

        $stream = fopen($gz, 'rb');
        $this->disk()->writeStream($target, $stream);
        fclose($stream);
        @unlink($gz);
    }

    private function gunzipFromDisk(string $snapshot, string $target): void
    {
        $gz = tempnam(sys_get_temp_dir(), 'gm-gz');
        $source = $this->disk()->readStream($snapshot);
        $local = fopen($gz, 'wb');
        stream_copy_to_stream($source, $local);
        fclose($local);
        if (is_resource($source)) {
            fclose($source);
        }

        $in = gzopen($gz, 'rb');
        $out = fopen($target, 'wb');
        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1024 * 1024));
        }
        gzclose($in);
        fclose($out);
        @unlink($gz);
    }

    private function mirrorPath(string $evidencePath): string
    {
        return $this->base($evidencePath);
    }

    private function base(string $path): string
    {
        return trim((string) config('genetic.backup.path'), '/').'/'.ltrim($path, '/');
    }
}
