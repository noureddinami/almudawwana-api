<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('moderator_id');
            $table->string('target_type', 50);
            $table->uuid('target_id');
            $table->string('action', 50);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('moderator_id');
            $table->foreign('moderator_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
