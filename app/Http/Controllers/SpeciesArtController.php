<?php

namespace App\Http\Controllers;

use App\Models\Species;
use App\Services\SpeciesArt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the render an admin uploaded for a species. It is decorative and
 * public. The version in the URL is part of the file name, so an old URL
 * stops working after a new upload and the current one can be cached for
 * good.
 */
class SpeciesArtController extends Controller
{
    public function __invoke(Species $species, string $version, SpeciesArt $art): StreamedResponse
    {
        abort_if($species->image === null || SpeciesArt::version($species->image) !== $version, 404);

        $disk = $art->disk();
        $path = $species->image;

        if (! $disk->exists($path)) {
            Log::error('Species image is missing.', ['species' => $species->code, 'path' => $path]);
            abort(404);
        }

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => (string) $disk->size($path),
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
