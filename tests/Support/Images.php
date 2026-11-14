<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Real image files for upload tests, built with GD.
 */
class Images
{
    public static function png(string $name = 'stats-screen.png', int $width = 800, int $height = 450): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    public static function jpeg(string $name = 'stats.jpg', int $width = 800, int $height = 450): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    public static function webp(string $name = 'stats.webp', int $width = 800, int $height = 450): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 90));
        ob_start();
        imagewebp($image, null, 80);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    /** A JPEG with an EXIF (APP1) segment that carries a marker string. */
    public static function jpegWithExif(string $marker, string $name = 'phone.jpg'): UploadedFile
    {
        $image = imagecreatetruecolor(320, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 60, 60));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();

        $payload = "Exif\0\0".'GPSLatitude='.$marker;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        // Insert right after the SOI marker (FFD8).
        $withExif = substr($jpeg, 0, 2).$segment.substr($jpeg, 2);

        return UploadedFile::fake()->createWithContent($name, $withExif);
    }

    /** A PNG with a tEXt chunk that carries a marker string. */
    public static function pngWithText(string $marker, string $name = 'meta.png'): UploadedFile
    {
        $image = imagecreatetruecolor(320, 200);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $data = "Comment\0".$marker;
        $chunk = pack('N', strlen($data)).'tEXt'.$data.pack('N', crc32('tEXt'.$data));
        // After the 8 byte signature and the IHDR chunk (8 + 25 bytes).
        $withText = substr($png, 0, 33).$chunk.substr($png, 33);

        return UploadedFile::fake()->createWithContent($name, $withText);
    }

    /**
     * A tiny PNG whose header claims huge dimensions. getimagesize() reads
     * the header only, so this is how a decompression bomb looks like.
     */
    public static function pngBomb(int $width = 8000, int $height = 6000, string $name = 'bomb.png'): UploadedFile
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', $ihdr).$chunk('IDAT', gzcompress(str_repeat("\0", 64))).$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    /** Real GIF content, whatever the file name says. */
    public static function gif(string $name = 'anim.gif'): UploadedFile
    {
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagegif($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    public static function svg(string $name = 'drawing.svg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>');
    }

    public static function phpRenamedToPng(string $name = 'shell.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "<?php echo shell_exec(\$_GET['c']); ?>\n");
    }

    public static function oversized(int $kilobytes = 16 * 1024, string $name = 'huge.png'): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'image/png');
    }
}
