<?php

namespace Database\Seeders;

use App\Services\Catalogue;
use Illuminate\Database\Seeder;

/**
 * Makes sure every species has a milestone for every stat (species × stats).
 * Species live in the database (the migration creates the first catalogue
 * from config/genetic.php, admins manage it on /admin/species). Idempotent:
 * it never deletes anything and never switches a milestone on or off, so it
 * runs on every deploy.
 */
class MilestoneSeeder extends Seeder
{
    public function run(): void
    {
        app(Catalogue::class)->sync();
    }
}
