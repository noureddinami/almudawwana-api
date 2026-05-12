<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisprudence_articles', function (Blueprint $table) {
            $table->uuid('jurisprudence_id');
            $table->uuid('article_id');
            $table->text('relevance_note')->nullable();

            $table->primary(['jurisprudence_id', 'article_id']);
            $table->foreign('jurisprudence_id')->references('id')->on('jurisprudence')->onDelete('cascade');
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisprudence_articles');
    }
};
