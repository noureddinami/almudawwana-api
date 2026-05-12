<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('commentary_id')->nullable();
            $table->uuid('discussion_id')->nullable();
            $table->tinyInteger('vote_type')->comment('1 ou -1');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'commentary_id']);
            $table->unique(['user_id', 'discussion_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('commentary_id')->references('id')->on('commentaries')->onDelete('cascade');
            $table->foreign('discussion_id')->references('id')->on('discussions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
