<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('source_article_id');
            $table->uuid('target_article_id');
            $table->enum('relation_type', ['cites', 'amends', 'abrogates', 'replaces', 'related']);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['source_article_id', 'target_article_id', 'relation_type'], 'uq_article_relation');
            $table->foreign('source_article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('target_article_id')->references('id')->on('articles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_relations');
    }
};
