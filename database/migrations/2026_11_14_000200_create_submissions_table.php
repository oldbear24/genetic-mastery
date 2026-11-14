<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every attempt a player makes. Nothing is ever deleted; decisions
        // only move the status forward (see App\Enums\SubmissionStatus).
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 16)->nullable()->unique();
            $table->foreignId('milestone_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Name at the time of sending, so history survives renames (HIS-3).
            $table->string('player_name_snapshot');
            $table->string('status', 16);
            $table->text('player_note')->nullable();
            $table->timestamp('submitted_at');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();

            $table->timestamp('withdrawn_at')->nullable();
            // The verified submission that closed this one as SUPERSEDED.
            // Revoking that verification reopens exactly these claims.
            $table->foreignId('superseded_by_id')->nullable()->constrained('submissions')->restrictOnDelete();

            $table->string('discord_message_id', 32)->nullable();
            $table->timestamps();

            $table->index(['milestone_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        // Integrity rules enforced by the database, not only by code. Partial
        // unique indexes have the same syntax in SQLite and PostgreSQL.
        //  - a milestone has at most one VERIFIED submission
        //  - a player has at most one PENDING claim per milestone
        DB::statement("CREATE UNIQUE INDEX submissions_one_verified_per_milestone ON submissions (milestone_id) WHERE status = 'verified'");
        DB::statement("CREATE UNIQUE INDEX submissions_one_pending_per_player ON submissions (milestone_id, user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
