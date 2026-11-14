<?php

namespace App\Rules;

use App\Services\Evidence\EvidenceProcessor;
use App\Services\Evidence\InvalidEvidence;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * One screenshot: PNG, JPEG or WEBP by content, within size and pixel
 * limits (UPL-1 to UPL-3). The pixels are decoded later, when storing.
 */
class EvidenceImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail(__('submissions.upload.failed', ['name' => '']));

            return;
        }

        try {
            app(EvidenceProcessor::class)->inspect($value);
        } catch (InvalidEvidence $e) {
            $fail($e->translated());
        }
    }
}
