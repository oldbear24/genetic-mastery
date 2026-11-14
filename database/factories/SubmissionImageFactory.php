<?php

namespace Database\Factories;

use App\Enums\EvidenceKind;
use App\Models\Submission;
use App\Models\SubmissionImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Database rows only, without files on disk. Upload tests go through the
 * real form and EvidenceProcessor instead.
 *
 * @extends Factory<SubmissionImage>
 */
class SubmissionImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'storage_key' => (string) Str::uuid(),
            'original_name' => 'screenshot.png',
            'mime' => 'image/png',
            'bytes' => 245_000,
            'width' => 2560,
            'height' => 1440,
            'sha256' => hash('sha256', Str::random(32)),
            'position' => 1,
            'kind' => EvidenceKind::Stats,
        ];
    }
}
