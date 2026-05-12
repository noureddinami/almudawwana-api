<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('code_id');
            $table->uuid('book_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->string('number', 20);
            $table->string('title_ar', 500);
            $table->string('title_fr', 500)->nullable();
            $table->tinyInteger('level')->default(1);
            $table->integer('display_order');
            $table->timestamps();

            $table->index('code_id');
            $table->index('parent_id');
            $table->foreign('code_id')->references('id')->on('codes')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('sections')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
