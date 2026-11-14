<?php

namespace App\Models;

use App\Enums\EvidenceKind;
use Database\Factories\SubmissionImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_id
 * @property string $storage_key
 * @property string $original_name
 * @property string $mime
 * @property int $bytes
 * @property int $width
 * @property int $height
 * @property string $sha256
 * @property int $position
 * @property EvidenceKind $kind
 * @property-read Submission $submission
 */
#[Fillable(['submission_id', 'storage_key', 'original_name', 'mime', 'bytes', 'width', 'height', 'sha256', 'position', 'kind'])]
class SubmissionImage extends Model
{
    /** @use HasFactory<SubmissionImageFactory> */
    use HasFactory;

    public const VARIANT_ORIGINAL = 'original';

    public const VARIANT_DISPLAY = 'display';

    public const VARIANT_THUMB = 'thumb';

    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'storage_key';
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public static function directoryFor(string $storageKey): string
    {
        return 'evidence/'.substr($storageKey, 0, 2).'/'.$storageKey;
    }

    /** Path on the evidence disk for a variant. */
    public function path(string $variant): string
    {
        return self::pathFor($this->storage_key, $variant, $this->mime);
    }

    public static function pathFor(string $storageKey, string $variant, string $mime): string
    {
        $directory = self::directoryFor($storageKey);

        return match ($variant) {
            self::VARIANT_ORIGINAL => "{$directory}/original",
            self::VARIANT_DISPLAY => "{$directory}/display.".self::extensionFor($mime),
            self::VARIANT_THUMB => "{$directory}/thumb.webp",
        };
    }

    public static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        };
    }

    /** MIME type of the stored variant. */
    public function variantMime(string $variant): string
    {
        return $variant === self::VARIANT_THUMB ? 'image/webp' : $this->mime;
    }
}
