<?php

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
 * Acceptance: two admins approve two claims on the same milestone at the
 * same moment. Exactly one is verified, the other admin gets an error and
 * the progress goes up by one.
 *
 * Runs two real PHP processes against a file based SQLite database (WAL,
 * BEGIN IMMEDIATE). On PostgreSQL the same guarantee comes from the row
 * lock and the unique partial index, covered in ApproveTest.
 */

function raceProcess(array $args, string $database): Process
{
    $process = new Process(
        [PHP_BINARY, base_path('tests/Support/approval-race.php'), ...$args],
        base_path(),
        ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'],
    );
    $process->setTimeout(60);

    return $process;
}

it('verifies exactly one of two claims approved at the same moment', function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Runs against a file based SQLite database.');
    }

    $database = tempnam(sys_get_temp_dir(), 'race').'.sqlite';
    touch($database);

    try {
        $setup = raceProcess(['setup'], $database);
        $setup->mustRun();
        $ids = json_decode($setup->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $startAt = (string) (microtime(true) + 1.5);
        $first = raceProcess(['approve', $ids['claimA'], $ids['adminA'], $startAt], $database);
        $second = raceProcess(['approve', $ids['claimB'], $ids['adminB'], $startAt], $database);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $results = [trim($first->getOutput()), trim($second->getOutput())];
        sort($results);

        expect($results[0])->toBe('ok')
            ->and($results[1])->toStartWith('refused: ')
            ->and($results[1])->not->toContain('error:');

        $state = raceProcess(['state'], $database);
        $state->mustRun();

        expect(json_decode($state->getOutput(), true))->toBe([
            'verified' => 1,
            'superseded' => 1,
            'pending' => 0,
            'progress' => 1,
            'outbox' => 1,
            'approved_audit' => 1,
        ]);
    } finally {
        foreach ([$database, "{$database}-wal", "{$database}-shm"] as $file) {
            @unlink($file);
        }
    }
});
