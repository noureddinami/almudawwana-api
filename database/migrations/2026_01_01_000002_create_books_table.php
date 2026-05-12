<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('code_id');
            $table->integer('number');
            $table->string('title_ar', 255);
            $table->string('title_fr', 255)->nullable();
            $table->integer('display_order');
            $table->timestamps();

            $table->unique(['code_id', 'number']);
            $table->foreign('code_id')->references('id')->on('codes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
