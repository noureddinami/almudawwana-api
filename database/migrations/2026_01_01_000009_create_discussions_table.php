<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->uuid('author_id');
            $table->uuid('parent_id')->nullable();
            $table->string('title', 255)->nullable();
            $table->text('content_ar');
            $table->tinyInteger('is_resolved')->default(0);
            $table->integer('upvotes')->default(0);
            $table->timestamps();

            $table->index('article_id');
            $table->index('parent_id');
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('discussions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
