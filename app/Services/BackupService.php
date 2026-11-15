<?php

namespace App\Services;

use App\Models\SubmissionImage;
use App\Services\Evidence\EvidenceProcessor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Backups (kap. Zálohy): a consistent database snapshot plus a mirror of the
 * evidence files and uploaded species images on the backup disk, snapshots
 * kept for 30 days. These files never change and are never deleted (UPL-9),
 * so the mirror only copies new files.
 *
 *  - SQLite: VACUUM INTO (consistent even while the app writes)
 *  - MySQL / MariaDB: a dump written in PHP (schema and data), because
 *    shared hosting such as WEDOS has no mysqldump and no exec(); it can be
 *    imported with the mysql client, phpMyAdmin or Adminer
 *  - PostgreSQL: pg_dump (needs proc_open)
 *
 * Every snapshot gets a manifest (row counts, evidence originals) that the
 * restore test checks. For a copy outside the server point BACKUP_DISK at an
 * off-site disk.
 */
class BackupService
{
    /** Tables whose content is transient; their schema is kept, rows are not. */
    private const SKIP_DATA = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

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
     * Restore test. SQLite snapshots are unpacked into a scratch database
     * and checked with PRAGMA integrity_check; MySQL dumps are read back and
     * their rows counted against the manifest. Every evidence original the
     * snapshot references has to be in the mirror.
     *
     * @return array{snapshot: string, ok: bool, integrity: string, milestones: int, submissions: int, images: int, missing_files: list<string>}
     */
    public function verify(?string $snapshot = null): array
    {
        $snapshot ??= $this->snapshots()[0] ?? null;

        if ($snapshot === null) {
            throw new RuntimeException('No database snapshot found.');
        }

        $manifest = $this->readManifest($snapshot);

        $result = match (true) {
            str_ends_with($snapshot, '.sqlite.gz') => $this->verifySqlite($snapshot),
            str_ends_with($snapshot, '.sql.gz') => $this->verifyMysqlDump($snapshot, $manifest),
            default => throw new RuntimeException('PostgreSQL dumps are verified with pg_restore into a scratch database.'),
        };

        $missing = [];
        foreach ($result['evidence'] as $path) {
            if (! $this->disk()->exists($this->base($path))) {
                $missing[] = $this->base($path);
            }
        }

        unset($result['evidence']);
        $result['snapshot'] = $snapshot;
        $result['missing_files'] = $missing;
        $result['ok'] = $result['integrity'] === 'ok' && $missing === [] && $result['milestones'] > 0;

        return $result;
    }

    /** @return list<string> snapshot paths, newest first */
    public function snapshots(): array
    {
        return collect($this->disk()->files($this->base('db')))
            ->filter(fn (string $path) => preg_match('/genetic-\d{8}-\d{6}\.(sqlite\.gz|sql\.gz|pgdump)$/', $path))
            ->sortDesc()
            ->values()
            ->all();
    }

    public function latestSnapshotTime(): ?Carbon
    {
        $latest = $this->snapshots()[0] ?? null;

        return $latest === null ? null : $this->timeOf($latest);
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('genetic.backup.disk'));
    }

    // --- snapshot ------------------------------------------------------------

    private function snapshotDatabase(string $stamp): string
    {
        $tmp = $this->tempPath('snapshot');

        try {
            $driver = DB::connection()->getDriverName();

            [$target, $counts] = match ($driver) {
                'sqlite' => $this->sqliteSnapshot($tmp, $stamp),
                'mysql', 'mariadb' => $this->mysqlSnapshot($tmp, $stamp),
                'pgsql' => $this->pgsqlSnapshot($tmp, $stamp),
                default => throw new RuntimeException("Backups are not implemented for the {$driver} driver."),
            };

            $this->disk()->put($this->manifestPath($target), json_encode([
                'created_at' => now()->utc()->toIso8601ZuluString(),
                'driver' => $driver,
                'tables' => $counts,
                'evidence' => $this->evidenceOriginals(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            @unlink($tmp);
        }

        return $target;
    }

    /** @return array{0: string, 1: array<string, int>} */
    private function sqliteSnapshot(string $tmp, string $stamp): array
    {
        DB::statement('VACUUM INTO ?', [$tmp]);
        $target = $this->base("db/genetic-{$stamp}.sqlite.gz");
        $this->gzipToDisk($tmp, $target);

        return [$target, $this->rowCounts()];
    }

    /** @return array{0: string, 1: array<string, int>} */
    private function mysqlSnapshot(string $tmp, string $stamp): array
    {
        $counts = $this->mysqlDump($tmp);
        $target = $this->base("db/genetic-{$stamp}.sql.gz");
        $this->copyToDisk($tmp, $target);

        return [$target, $counts];
    }

    /** @return array{0: string, 1: array<string, int>} */
    private function pgsqlSnapshot(string $tmp, string $stamp): array
    {
        if (! function_exists('proc_open')) {
            throw new RuntimeException('PostgreSQL backups need pg_dump and proc_open, which this host does not allow.');
        }

        $config = DB::connection()->getConfig();
        $process = new Process(
            ['pg_dump', '--format=custom', '--no-owner', '--file='.$tmp,
                '--host='.$config['host'], '--port='.$config['port'], '--username='.$config['username'], $config['database']],
            null,
            ['PGPASSWORD' => (string) ($config['password'] ?? '')],
        );
        $process->setTimeout(600);
        $process->mustRun();

        $target = $this->base("db/genetic-{$stamp}.pgdump");
        $this->copyToDisk($tmp, $target);

        return [$target, $this->rowCounts()];
    }

    /**
     * Schema and data as SQL, gzip compressed, one INSERT per row. Generated
     * columns are left out of the INSERTs (MySQL computes them).
     *
     * @return array<string, int> rows per table
     */
    private function mysqlDump(string $file): array
    {
        $pdo = DB::connection()->getPdo();
        $out = gzopen($file, 'wb6');
        $counts = [];

        gzwrite($out, '-- Genetic Mastery database dump, '.now()->utc()->toIso8601ZuluString()."\n");
        gzwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET time_zone='+00:00';\n\n");

        $tables = collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->sort()
            ->values();

        foreach ($tables as $table) {
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
            gzwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

            if (in_array($table, self::SKIP_DATA, true)) {
                continue;
            }

            $columns = collect(DB::select(
                'SELECT COLUMN_NAME AS name, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                [$table],
            ))->reject(fn ($c) => str_contains(strtoupper((string) $c->extra), 'GENERATED'))->pluck('name')->all();

            $columnList = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
            $counts[$table] = 0;

            foreach (DB::table($table)->select($columns)->cursor() as $row) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values((array) $row));
                gzwrite($out, "INSERT INTO `{$table}` ({$columnList}) VALUES (".implode(', ', $values).");\n");
                $counts[$table]++;
            }

            gzwrite($out, "\n");
        }

        // Triggers last and without DEFINER, so the dump imports under any user.
        foreach (DB::select('SHOW TRIGGERS') as $trigger) {
            $name = ((array) $trigger)['Trigger'];
            $sql = ((array) DB::selectOne("SHOW CREATE TRIGGER `{$name}`"))['SQL Original Statement'];
            $sql = preg_replace('/DEFINER\s*=\s*`[^`]*`@`[^`]*`\s*/i', '', $sql);
            gzwrite($out, "DROP TRIGGER IF EXISTS `{$name}`;\n{$sql};\n");
        }

        gzwrite($out, "\nSET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n-- Dump completed\n");
        gzclose($out);

        return $counts;
    }

    /** @return array<string, int> */
    private function rowCounts(): array
    {
        $counts = [];
        foreach (['users', 'species', 'milestones', 'submissions', 'submission_images', 'submission_notes', 'audit_log', 'discord_outbox'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /** @return list<string> evidence original paths (relative to the evidence disk) */
    private function evidenceOriginals(): array
    {
        return SubmissionImage::query()->orderBy('id')->get(['storage_key', 'mime'])
            ->map(fn (SubmissionImage $i) => SubmissionImage::pathFor($i->storage_key, SubmissionImage::VARIANT_ORIGINAL, $i->mime))
            ->all();
    }

    // --- verify ----------------------------------------------------------------

    /** @return array{integrity: string, milestones: int, submissions: int, images: int, evidence: list<string>} */
    private function verifySqlite(string $snapshot): array
    {
        $tmp = $this->tempPath('restore');
        $this->gunzipFromDisk($snapshot, $tmp);

        try {
            $pdo = new PDO('sqlite:'.$tmp);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $count = fn (string $table) => (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

            $evidence = array_map(
                fn (array $i) => SubmissionImage::pathFor($i['storage_key'], SubmissionImage::VARIANT_ORIGINAL, $i['mime']),
                $pdo->query('SELECT storage_key, mime FROM submission_images')->fetchAll(PDO::FETCH_ASSOC),
            );

            $result = [
                'integrity' => (string) $pdo->query('PRAGMA integrity_check')->fetchColumn(),
                'milestones' => $count('milestones'),
                'submissions' => $count('submissions'),
                'images' => count($evidence),
                'evidence' => $evidence,
            ];
            $pdo = null;

            return $result;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Reads the dump back: it has to be complete (footer present) and hold
     * exactly the rows the manifest promises for every table.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{integrity: string, milestones: int, submissions: int, images: int, evidence: list<string>}
     */
    private function verifyMysqlDump(string $snapshot, array $manifest): array
    {
        $tmp = $this->tempPath('restore');
        $this->gunzipFromDisk($snapshot, $tmp);

        try {
            $found = [];
            $creates = [];
            $completed = false;
            $handle = fopen($tmp, 'rb');

            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^INSERT INTO `([^`]+)`/', $line, $m)) {
                    $found[$m[1]] = ($found[$m[1]] ?? 0) + 1;
                } elseif (preg_match('/^CREATE TABLE `([^`]+)`/', $line, $m)) {
                    $creates[] = $m[1];
                } elseif (str_starts_with($line, '-- Dump completed')) {
                    $completed = true;
                }
            }
            fclose($handle);
        } finally {
            @unlink($tmp);
        }

        $problems = [];
        if (! $completed) {
            $problems[] = 'dump is incomplete';
        }
        foreach (($manifest['tables'] ?? []) as $table => $count) {
            if (! in_array($table, $creates, true)) {
                $problems[] = "missing table {$table}";
            }
            if (($found[$table] ?? 0) !== (int) $count) {
                $problems[] = "{$table}: ".($found[$table] ?? 0)." rows, manifest says {$count}";
            }
        }

        return [
            'integrity' => $problems === [] ? 'ok' : implode('; ', $problems),
            'milestones' => $found['milestones'] ?? 0,
            'submissions' => $found['submissions'] ?? 0,
            'images' => $found['submission_images'] ?? 0,
            'evidence' => $manifest['evidence'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function readManifest(string $snapshot): array
    {
        $path = $this->manifestPath($snapshot);

        return $this->disk()->exists($path) ? (array) json_decode((string) $this->disk()->get($path), true) : [];
    }

    // --- evidence mirror and retention ------------------------------------------

    /**
     * Copy evidence files and uploaded species images that are not in the
     * mirror yet. Neither is ever changed in place: a new species image gets
     * a new file name.
     */
    private function mirrorEvidence(): int
    {
        $source = $this->evidence->disk();
        $target = $this->disk();
        $copied = 0;

        foreach ([...$source->allFiles('evidence'), ...$source->allFiles('species')] as $path) {
            $mirror = $this->base($path);

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

    /** Delete snapshots (and their manifests) older than the retention period. */
    private function prune(): int
    {
        $cutoff = now()->utc()->subDays((int) config('genetic.backup.retention_days'));
        $pruned = 0;

        foreach ($this->snapshots() as $path) {
            if ($this->timeOf($path)->lt($cutoff)) {
                $this->disk()->delete([$path, $this->manifestPath($path)]);
                $pruned++;
            }
        }

        return $pruned;
    }

    // --- helpers -----------------------------------------------------------------

    private function timeOf(string $snapshot): Carbon
    {
        preg_match('/genetic-(\d{8}-\d{6})\./', $snapshot, $m);

        return Carbon::createFromFormat('Ymd-His', $m[1], 'UTC');
    }

    private function manifestPath(string $snapshot): string
    {
        return preg_replace('/\.(sqlite\.gz|sql\.gz|pgdump)$/', '.manifest.json', $snapshot);
    }

    /**
     * Temporary files live under storage/, because open_basedir on shared
     * hosting usually forbids the system temp directory.
     */
    private function tempPath(string $prefix): string
    {
        $dir = storage_path('app/tmp');
        File::ensureDirectoryExists($dir);

        return $dir.'/'.$prefix.'-'.bin2hex(random_bytes(8));
    }

    private function copyToDisk(string $file, string $target): void
    {
        $stream = fopen($file, 'rb');
        $this->disk()->writeStream($target, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private function gzipToDisk(string $file, string $target): void
    {
        $gz = $this->tempPath('gz');
        $in = fopen($file, 'rb');
        $out = gzopen($gz, 'wb6');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);

        $this->copyToDisk($gz, $target);
        @unlink($gz);
    }

    private function gunzipFromDisk(string $snapshot, string $target): void
    {
        $gz = $this->tempPath('gz');
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

    private function base(string $path): string
    {
        return trim((string) config('genetic.backup.path'), '/').'/'.ltrim($path, '/');
    }
}
