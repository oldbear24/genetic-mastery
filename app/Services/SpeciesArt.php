<?php

namespace App\Services;

use App\Services\Evidence\EvidenceProcessor;
use App\Services\Evidence\InvalidEvidence;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

/**
 * Species renders uploaded by admins. The upload goes through the same
 * checks as evidence (type from the content, size and megapixel limits),
 * then it is scaled down to fit MAX_SIZE, optionally mirrored (renders face
 * right next to the species name) and re-encoded as WebP with transparency
 * kept and without metadata.
 *
 * Files live on the evidence disk under species/{CODE}/{random}.webp, so
 * they survive deploys (Docker volume, shared hosting storage) and are part
 * of the backup mirror. Every upload gets a new name, which makes the URL
 * change and lets browsers cache an image for good. Replaced files are kept
 * like everything else the audit log refers to.
 */
class SpeciesArt
{
    /** Longest side in pixels; the bundled renders are 400 px too. */
    public const MAX_SIZE = 400;

    public function __construct(private readonly EvidenceProcessor $evidence) {}

    /**
     * @return string the storage key for Species::$image
     *
     * @throws InvalidEvidence when the file is not an acceptable image
     */
    public function store(UploadedFile $file, string $code, bool $flip = false): string
    {
        $info = $this->evidence->inspect($file);
        $image = $this->evidence->decode((string) $file->getRealPath(), $info, $this->evidence->displayName($file));
        $image = $this->fit($image, $info['width'], $info['height']);

        if ($flip) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $key = 'species/'.$code.'/'.bin2hex(random_bytes(8)).'.webp';
        $this->disk()->put($key, $this->encode($image));

        return $key;
    }

    /** Remove a file that never made it into the database (the save failed). */
    public function discard(string $key): void
    {
        $this->disk()->delete($key);
    }

    public function disk(): Filesystem
    {
        return $this->evidence->disk();
    }

    /** The random part of a storage key, used in the URL. */
    public static function version(string $key): string
    {
        return pathinfo($key, PATHINFO_FILENAME);
    }

    /** A true colour copy with alpha that fits into MAX_SIZE × MAX_SIZE. */
    private function fit(GdImage $image, int $width, int $height): GdImage
    {
        $scale = min(1, self::MAX_SIZE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function encode(GdImage $image): string
    {
        ob_start();

        try {
            $ok = imagewebp($image, null, 85);
        } finally {
            $data = (string) ob_get_clean();
        }

        if (! $ok || $data === '') {
            throw new InvalidEvidence('processing_failed');
        }

        return $data;
    }
}
