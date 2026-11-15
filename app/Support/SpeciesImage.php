<?php

namespace App\Support;

/**
 * Decorative species renders in public/images/species, named after the
 * lowercase species code (REX → rex.webp). Species without a file simply
 * render without an image, so the species list in config can change freely.
 */
class SpeciesImage
{
    public static function url(string $code): ?string
    {
        $path = 'images/species/'.strtolower($code).'.webp';

        return is_file(public_path($path)) ? asset($path) : null;
    }
}
