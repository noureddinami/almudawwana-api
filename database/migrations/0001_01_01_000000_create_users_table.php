<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->unique();
            $table->string('username', 50)->unique()->nullable();
            $table->string('full_name', 150)->nullable();
            $table->string('full_name_ar', 150)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('google_id', 100)->unique()->nullable();
            $table->text('google_token')->nullable();
            $table->text('google_avatar')->nullable();
            $table->enum('auth_provider', ['email', 'google'])->default('email');
            $table->text('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->string('profession', 100)->nullable();
            $table->enum('role', ['reader', 'contributor', 'moderator', 'admin'])->default('reader');
            $table->enum('status', ['active', 'suspended', 'banned', 'pending'])->default('pending');
            $table->integer('karma_points')->default(0);
            $table->tinyInteger('is_verified_jurist')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
