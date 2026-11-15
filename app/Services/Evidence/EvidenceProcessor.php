<?php

namespace App\Services\Evidence;

use App\Models\SubmissionImage;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Checks and stores uploaded screenshots.
 *
 *  - the type comes from the file content (finfo and the image header), never
 *    from the extension or the browser's Content-Type (UPL-1)
 *  - dimensions are read from the header before decoding, images over the
 *    megapixel limit are refused before they can blow up memory (UPL-3)
 *  - the original goes to the private disk under a random UUID (UPL-4)
 *  - a re-encoded full resolution copy without metadata and a thumbnail are
 *    what the site shows (UPL-5); GD writes no EXIF or GPS data
 *  - the SHA-256 of the original is kept to spot reused images (UPL-7)
 *  - everything goes through Laravel Storage, so the disk can become S3 (UPL-8)
 */
class EvidenceProcessor
{
    /** MIME type => GD image type */
    public const ALLOWED = [
        'image/png' => IMAGETYPE_PNG,
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    /**
     * Validate one upload without decoding the pixels.
     *
     * @return array{mime: string, width: int, height: int, bytes: int}
     *
     * @throws InvalidEvidence
     */
    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new InvalidEvidence('failed', ['name' => $this->displayName($file)]);
        }

        $path = $file->getRealPath();
        $bytes = (int) $file->getSize();
        $maxBytes = (int) config('genetic.upload.max_mb') * 1024 * 1024;

        if ($bytes > $maxBytes) {
            throw new InvalidEvidence('too_large', ['name' => $this->displayName($file), 'max' => config('genetic.upload.max_mb')]);
        }

        if ($bytes === 0 || $path === false) {
            throw new InvalidEvidence('not_image', ['name' => $this->displayName($file)]);
        }

        // 1) MIME type from the content (magic bytes).
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';

        // 2) The image header must agree. getimagesize() only reads the
        //    header, so a 40+ megapixel bomb is caught before decoding.
        $info = @getimagesize($path);

        if (! array_key_exists($detected, self::ALLOWED) || $info === false || $info[2] !== self::ALLOWED[$detected]) {
            throw new InvalidEvidence('wrong_type', ['name' => $this->displayName($file)]);
        }

        [$width, $height] = $info;

        if ($width < 1 || $height < 1) {
            throw new InvalidEvidence('not_image', ['name' => $this->displayName($file)]);
        }

        $maxPixels = (int) config('genetic.upload.max_megapixels') * 1_000_000;

        if ($width * $height > $maxPixels) {
            throw new InvalidEvidence('too_many_pixels', [
                'name' => $this->displayName($file),
                'max' => config('genetic.upload.max_megapixels'),
            ]);
        }

        return ['mime' => $detected, 'width' => $width, 'height' => $height, 'bytes' => $bytes];
    }

    /**
     * Store the original, the clean copy and the thumbnail.
     *
     * @param  array{mime: string, width: int, height: int, bytes: int}  $info  from inspect()
     * @return array{storage_key: string, original_name: string, mime: string, bytes: int, width: int, height: int, sha256: string}
     *
     * @throws InvalidEvidence when the pixels cannot be decoded
     */
    public function store(UploadedFile $file, array $info): array
    {
        $path = (string) $file->getRealPath();
        $key = (string) Str::uuid();
        $disk = $this->disk();

        $image = $this->decode($path, $info, $this->displayName($file));
        $display = $this->encode($image, $info['mime']);
        $thumb = $this->encode($this->thumbnail($image, $info['width'], $info['height']), 'image/webp', 80);
        unset($image);

        $stream = fopen($path, 'rb');

        try {
            $disk->writeStream(SubmissionImage::pathFor($key, SubmissionImage::VARIANT_ORIGINAL, $info['mime']), $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $disk->put(SubmissionImage::pathFor($key, SubmissionImage::VARIANT_DISPLAY, $info['mime']), $display);
        $disk->put(SubmissionImage::pathFor($key, SubmissionImage::VARIANT_THUMB, $info['mime']), $thumb);

        return [
            'storage_key' => $key,
            'original_name' => $this->displayName($file),
            'mime' => $info['mime'],
            'bytes' => $info['bytes'],
            'width' => $info['width'],
            'height' => $info['height'],
            'sha256' => hash_file('sha256', $path),
        ];
    }

    /**
     * Remove stored files for keys that never made it into the database
     * (the insert failed). Files of saved submissions are never deleted.
     *
     * @param  list<string>  $storageKeys
     */
    public function discard(array $storageKeys): void
    {
        foreach ($storageKeys as $key) {
            $this->disk()->deleteDirectory(SubmissionImage::directoryFor($key));
        }
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('genetic.upload.disk'));
    }

    /** The original file name, cleaned up for storing and showing. */
    public function displayName(UploadedFile $file): string
    {
        $name = Str::of($file->getClientOriginalName())
            ->replaceMatches('/[\x00-\x1F\x7F]/u', '')
            ->basename()
            ->trim()
            ->limit(180, '');

        return $name->isEmpty() ? 'image' : (string) $name;
    }

    /**
     * Decode the pixels of a file that passed inspect().
     *
     * @param  array{mime: string, width: int, height: int, bytes: int}  $info
     *
     * @throws InvalidEvidence
     */
    public function decode(string $path, array $info, string $name): GdImage
    {
        $this->ensureMemoryFor($info['width'], $info['height']);

        $image = match ($info['mime']) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => @imagecreatefromwebp($path),
        };

        if (! $image instanceof GdImage) {
            throw new InvalidEvidence('not_image', ['name' => $name]);
        }

        return $image;
    }

    private function encode(GdImage $image, string $mime, int $quality = 90): string
    {
        ob_start();

        try {
            $ok = match ($mime) {
                'image/png' => $this->encodePng($image),
                'image/jpeg' => imagejpeg($image, null, $quality),
                'image/webp' => imagewebp($image, null, $quality),
            };
        } finally {
            $data = (string) ob_get_clean();
        }

        if (! $ok || $data === '') {
            throw new InvalidEvidence('processing_failed');
        }

        return $data;
    }

    private function encodePng(GdImage $image): bool
    {
        imagesavealpha($image, true);

        return imagepng($image, null, 6);
    }

    private function thumbnail(GdImage $image, int $width, int $height): GdImage
    {
        $maxWidth = (int) config('genetic.upload.thumbnail_width');

        if ($width <= $maxWidth) {
            return $image;
        }

        $thumbHeight = max(1, (int) round($height * $maxWidth / $width));
        $thumb = imagecreatetruecolor($maxWidth, $thumbHeight);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $maxWidth, $thumbHeight, $width, $height);

        return $thumb;
    }

    /** GD needs roughly 5 bytes per pixel plus room for the copies. */
    private function ensureMemoryFor(int $width, int $height): void
    {
        $needed = (int) ($width * $height * 5 * 1.5) + 64 * 1024 * 1024;
        $current = $this->toBytes((string) ini_get('memory_limit'));

        if ($current !== -1 && $current < $needed) {
            ini_set('memory_limit', (string) min($needed, 1024 * 1024 * 1024));
        }
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
