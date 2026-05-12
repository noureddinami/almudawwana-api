<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('code_id')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->string('title_ar', 300);
            $table->string('title_fr', 300)->nullable();
            $table->string('original_filename', 500);
            $table->string('stored_filename', 500);
            $table->string('disk', 50)->default('public');
            $table->unsignedBigInteger('file_size')->default(0)->comment('en octets');
            $table->enum('status', ['pending', 'processing', 'imported', 'failed'])->default('pending');
            $table->integer('articles_extracted')->default(0);
            $table->text('extraction_log')->nullable();
            $table->string('source_url', 1000)->nullable()->comment('URL Adala si téléchargé depuis le web');
            $table->enum('document_type', [
                'code', 'law', 'decree', 'order', 'circular', 'other'
            ])->default('code');
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index('code_id');
            $table->index('status');
            $table->foreign('code_id')->references('id')->on('codes')->onDelete('set null');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_documents');
    }
};
