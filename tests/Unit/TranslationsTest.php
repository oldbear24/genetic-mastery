<?php

use App\Enums\EvidenceKind;
use App\Enums\MilestoneState;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/*
 * Every literal translation key used in PHP code and Blade templates has to
 * exist in lang/cs, so no raw key like "admin.detail.title" reaches the UI.
 */

function usedTranslationKeys(): array
{
    $files = collect([app_path(), resource_path('views')])
        ->flatMap(fn (string $dir) => File::allFiles($dir))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'));

    $keys = [];
    foreach ($files as $file) {
        preg_match_all("/(?:__|trans_choice|trans|Lang::get)\\(\\s*'([a-z_]+\\.[a-z0-9_.]+)'/", $file->getContents(), $matches);
        foreach ($matches[1] as $key) {
            // Skip dynamic prefixes such as __("submissions.status.{$x}").
            if (! str_ends_with($key, '.')) {
                $keys[$key][] = $file->getRelativePathname();
            }
        }
    }

    return $keys;
}

it('has a Czech translation for every key used in the code', function () {
    $missing = collect(usedTranslationKeys())
        ->reject(fn ($files, string $key) => Lang::has($key, 'cs', false))
        ->map(fn ($files, string $key) => $key.' ('.implode(', ', array_unique($files)).')')
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('has a label for every audit action and every status', function () {
    foreach (AuditLog::ACTIONS as $action) {
        expect(AuditLog::labelFor($action))->not->toStartWith('audit.');
    }

    foreach (SubmissionStatus::cases() as $status) {
        expect($status->label())->not->toStartWith('submissions.');
    }

    foreach (MilestoneState::cases() as $state) {
        expect($state->label())->not->toStartWith('milestones.');
    }

    foreach (EvidenceKind::cases() as $kind) {
        expect($kind->label())->not->toStartWith('submissions.');
    }
});
