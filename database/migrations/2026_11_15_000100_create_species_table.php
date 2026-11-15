<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The species catalogue moves from config to the database, so admins can
 * add, rename, reorder and switch off species and single stats.
 *
 * Species from config/genetic.php become the initial, active catalogue.
 * Species that already have milestones but are no longer in config (the
 * first catalogue had Spinosaurus, Allosaurus, Yutyrannus and Megalosaurus)
 * are kept as inactive rows: their claims and evidence stay, they just stop
 * counting. Nothing is deleted.
 */
return new class extends Migration
{
    /** Names of codes from the first catalogue that are no longer in config. */
    private const RETIRED_NAMES = [
        'SPINO' => 'Spinosaurus',
        'ALLO' => 'Allosaurus',
        'YUTY' => 'Yutyrannus',
        'MEGALO' => 'Megalosaurus',
    ];

    public function up(): void
    {
        Schema::create('species', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            // Uploaded render on the evidence disk (species/{CODE}/{hex}.webp);
            // null = the bundled public/images/species file, if there is one.
            $table->string('image')->nullable();
            $table->timestamps();

            $table->index('sort_order');
        });

        // A switched off stat of an active species (e.g. Oxygen of a sea creature).
        Schema::table('milestones', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        $now = now();
        $order = 0;
        $rows = [];

        foreach ((array) config('genetic.species', []) as $code => $name) {
            $rows[$code] = ['code' => $code, 'name' => $name, 'sort_order' => ++$order, 'is_active' => true];
        }

        $existing = DB::table('milestones')->select('dinosaur')->distinct()->orderBy('dinosaur')->pluck('dinosaur');

        foreach ($existing as $code) {
            $rows[$code] ??= ['code' => $code, 'name' => self::RETIRED_NAMES[$code] ?? $code, 'sort_order' => ++$order, 'is_active' => false];
        }

        foreach ($rows as $row) {
            DB::table('species')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('species');
    }
};
