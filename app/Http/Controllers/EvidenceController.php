<?php

namespace App\Http\Controllers;

use App\Models\SubmissionImage;
use App\Services\Evidence\EvidenceProcessor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves evidence after checking permissions (UPL-6). Copies of verified
 * submissions are public, other copies only for the author and admins,
 * originals only for admins and always as a download.
 */
class EvidenceController extends Controller
{
    public function show(SubmissionImage $image, string $variant, EvidenceProcessor $evidence): StreamedResponse
    {
        $submission = $image->submission;

        if ($variant === SubmissionImage::VARIANT_ORIGINAL) {
            Gate::authorize('downloadOriginal', $submission);
        } else {
            Gate::authorize('view', $submission);
        }

        $disk = $evidence->disk();
        $path = $image->path($variant);

        if (! $disk->exists($path)) {
            Log::error('Evidence file is missing.', ['submission' => $submission->public_id, 'image_id' => $image->id, 'variant' => $variant]);
            abort(404);
        }

        $headers = [
            'Content-Type' => $image->variantMime($variant),
            'Content-Length' => (string) $disk->size($path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if ($variant === SubmissionImage::VARIANT_ORIGINAL) {
            $filename = $submission->public_id.'-'.$image->position.'-original.'.SubmissionImage::extensionFor($image->mime);
            $headers['Content-Disposition'] = 'attachment; filename="'.$filename.'"';
        } else {
            $headers['Content-Disposition'] = 'inline';
        }

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }
}
