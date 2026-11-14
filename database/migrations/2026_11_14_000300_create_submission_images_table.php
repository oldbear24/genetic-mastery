<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evidence for a submission. Files live outside the web root under a
        // random UUID (storage_key); the original name is only data (UPL-4).
        // Images are never deleted when the submission changes state (UPL-9).
        Schema::create('submission_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->uuid('storage_key')->unique();
            $table->string('original_name');
            // Detected from the file content, not from the browser (UPL-1).
            $table->string('mime', 32);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            // Hash of the original file, used to spot reused screenshots (UPL-7, ADM-4).
            $table->char('sha256', 64)->index();
            $table->unsignedTinyInteger('position');
            // stats | ancestry | other (SUB-2)
            $table->string('kind', 16);
            $table->timestamps();

            $table->unique(['submission_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_images');
    }
};
