<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Admin note on a submission (OPR-5). Plain text, always escaped (SEC-3).
 *
 * @property int $id
 * @property int $submission_id
 * @property int $author_id
 * @property string $visibility public|internal
 * @property string $body
 * @property Carbon $created_at
 * @property-read User $author
 * @property-read Submission $submission
 */
#[Fillable(['submission_id', 'author_id', 'visibility', 'body', 'created_at'])]
class SubmissionNote extends Model
{
    public const UPDATED_AT = null;

    public const PUBLIC = 'public';

    public const INTERNAL = 'internal';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::PUBLIC;
    }
}
