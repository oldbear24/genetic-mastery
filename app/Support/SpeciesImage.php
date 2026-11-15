<?php

namespace App\Support;

use App\Models\Species;
use App\Services\SpeciesArt;

/**
 * Where the decorative render of a species comes from:
 *
 *  1. an image an admin uploaded on /admin/species (served by
 *     SpeciesArtController, the URL changes with every upload)
 *  2. a bundled render in public/images/species, named after the lowercase
 *     species code (REX → rex.webp)
 *  3. nothing: the species renders without an image
 */
class SpeciesImage
{
    public static function for(Species $species): ?string
    {
        if ($species->image !== null) {
            return route('species.art', ['species' => $species->code, 'version' => SpeciesArt::version($species->image)]);
        }

        return self::bundled($species->code);
    }

    public static function bundled(string $code): ?string
    {
        $path = 'images/species/'.strtolower($code).'.webp';

        return is_file(public_path($path)) ? asset($path) : null;
    }
}
