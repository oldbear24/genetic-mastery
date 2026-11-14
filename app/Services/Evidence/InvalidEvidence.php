<?php

namespace App\Services\Evidence;

use RuntimeException;

/**
 * An uploaded file that is not acceptable evidence. The message is a
 * translation key from lang/cs/submissions.php (upload.*).
 */
class InvalidEvidence extends RuntimeException
{
    /** @param array<string, mixed> $replace */
    public function __construct(public readonly string $key, public readonly array $replace = [])
    {
        parent::__construct($key);
    }

    public function translated(): string
    {
        return __("submissions.upload.{$this->key}", $this->replace);
    }
}
