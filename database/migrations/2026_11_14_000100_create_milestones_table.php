<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A fixed catalogue of 54 rows created by the seeder. There is no
        // status column: the state is derived from submissions.
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('dinosaur', 32);
            $table->string('stat', 32);
            $table->unsignedInteger('required_value');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['dinosaur', 'stat']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
