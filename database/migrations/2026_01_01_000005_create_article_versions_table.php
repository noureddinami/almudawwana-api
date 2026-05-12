<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->integer('version_number');
            $table->text('content_ar');
            $table->string('amendment_law', 100)->nullable();
            $table->string('amendment_bo', 50)->nullable();
            $table->date('amendment_date')->nullable();
            $table->text('change_summary')->nullable();
            $table->tinyInteger('is_current')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['article_id', 'version_number']);
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_versions');
    }
};
