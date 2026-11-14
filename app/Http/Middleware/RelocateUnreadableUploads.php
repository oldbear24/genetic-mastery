<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Under open_basedir (shared hosting) PHP may store uploads in a temp
 * directory the script is not allowed to read. move_uploaded_file() still
 * works, so such files are moved into storage/app/tmp before validation and
 * deleted after the request. On normal hosts nothing happens.
 */
class RelocateUnreadableUploads
{
    /** @var (callable(string): bool)|null Test hook: decides whether a temp file counts as readable. */
    public static $readable = null;

    /** @var list<string> */
    private array $moved = [];

    public function handle(Request $request, Closure $next): Response
    {
        $this->relocate($request->files);

        try {
            return $next($request);
        } finally {
            foreach ($this->moved as $path) {
                @unlink($path);
            }
        }
    }

    /** @param FileBag|array<mixed> $files */
    private function relocate($files): void
    {
        $bag = $files instanceof FileBag ? $files : null;
        $items = $bag ? $bag->all() : $files;

        foreach ($items as $key => $value) {
            $replacement = $this->replace($value);

            if ($bag !== null && $replacement !== $value) {
                $bag->set($key, $replacement);
            }
        }
    }

    private function replace(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->replace($item), $value);
        }

        if (! $value instanceof UploadedFile || ! $value->isValid() || $this->isReadable($value->getPathname())) {
            return $value;
        }

        $dir = storage_path('app/tmp');
        File::ensureDirectoryExists($dir);
        $target = $dir.'/upload-'.bin2hex(random_bytes(12));

        if (! @move_uploaded_file($value->getPathname(), $target) && ! @rename($value->getPathname(), $target)) {
            return $value;
        }

        $this->moved[] = $target;

        return new UploadedFile($target, $value->getClientOriginalName(), $value->getClientMimeType(), UPLOAD_ERR_OK, true);
    }

    private function isReadable(string $path): bool
    {
        return static::$readable !== null ? (bool) (static::$readable)($path) : @is_readable($path);
    }
}
