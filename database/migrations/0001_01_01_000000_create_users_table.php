<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Discord snowflakes are 64-bit, stored as text to stay portable.
            $table->string('discord_id', 32)->unique();
            $table->string('username');
            $table->string('global_name')->nullable();
            $table->string('guild_nickname')->nullable();
            // Server nickname, otherwise global_name, otherwise username (AUTH-5).
            $table->string('display_name');
            $table->string('avatar_hash')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_member')->default(true);
            $table->timestamp('roles_checked_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
