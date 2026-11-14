<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every admin action with old and new values (OPR-7). Append only (SEC-6).
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('entity', 32);
            $table->unsignedBigInteger('entity_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['entity', 'entity_id']);
            $table->index('action');
            $table->index('created_at');
        });

        // Admin notes on a submission: public ones show in the history,
        // internal ones only to admins (OPR-5).
        Schema::create('submission_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('visibility', 16);
            $table->text('body');
            $table->timestamp('created_at');

            $table->index(['submission_id', 'visibility']);
        });

        // Messages for Discord, written in the same transaction as the admin
        // decision and sent later by the scheduler (DC-4, DC-5).
        Schema::create('discord_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->foreignId('submission_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('message_id', 32)->nullable();
            $table->timestamp('available_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
        });

        $this->protectAuditLog();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_log_append_only ON audit_log');
            DB::statement('DROP FUNCTION IF EXISTS audit_log_append_only()');
        }

        Schema::dropIfExists('discord_outbox');
        Schema::dropIfExists('submission_notes');
        Schema::dropIfExists('audit_log');
    }

    /**
     * Reject UPDATE and DELETE on audit_log in the database itself, on top
     * of the guard in the AuditLog model. Trigger syntax differs per driver.
     */
    private function protectAuditLog(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->sqliteTriggers(),
            'pgsql' => $this->pgsqlTrigger(),
            'mysql', 'mariadb' => $this->mysqlTriggers(),
            default => null,
        };
    }

    /**
     * Shared hosting often does not grant the TRIGGER privilege (or needs
     * SUPER with binary logging). Then the model guard alone protects the
     * log and the migration says so instead of failing the whole install.
     */
    private function mysqlTriggers(): void
    {
        try {
            DB::unprepared("CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only'");
            DB::unprepared("CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only'");
        } catch (QueryException $e) {
            Log::warning('Could not create audit_log triggers; the AuditLog model still refuses updates and deletes.', ['error' => $e->getMessage()]);
        }
    }

    private function sqliteTriggers(): void
    {
        DB::statement("CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON audit_log BEGIN SELECT RAISE(ABORT, 'audit_log is append-only'); END");
        DB::statement("CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON audit_log BEGIN SELECT RAISE(ABORT, 'audit_log is append-only'); END");
    }

    private function pgsqlTrigger(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_log_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log is append-only';
            END;
            $$ LANGUAGE plpgsql;
            SQL);
        DB::unprepared('CREATE TRIGGER audit_log_append_only BEFORE UPDATE OR DELETE ON audit_log FOR EACH ROW EXECUTE FUNCTION audit_log_append_only()');
    }
};
