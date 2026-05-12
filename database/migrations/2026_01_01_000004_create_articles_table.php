<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('code_id');
            $table->uuid('section_id')->nullable();
            $table->string('number', 20);
            $table->integer('number_int')->nullable();
            $table->string('slug', 150)->unique();
            $table->text('content_ar');
            $table->text('content_fr')->nullable();
            $table->enum('status', ['in_force', 'abrogated', 'amended', 'draft'])->default('in_force');
            $table->integer('current_version')->default(1);
            $table->integer('view_count')->default(0);
            $table->integer('bookmark_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->date('last_amended_date')->nullable();
            $table->date('abrogated_date')->nullable();
            $table->string('source', 255)->default('sgg.gov.ma');
            $table->timestamps();

            $table->unique(['code_id', 'number']);
            $table->index('section_id');
            $table->index(['code_id', 'number_int']);
            $table->index('status');
            $table->foreign('code_id')->references('id')->on('codes')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
