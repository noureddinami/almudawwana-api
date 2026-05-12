<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('title_ar', 255);
            $table->string('title_fr', 255)->nullable();
            $table->text('description_ar')->nullable();
            $table->enum('type', [
                'constitution', 'organic_law', 'ordinary_law', 'code',
                'decree_law', 'decree', 'order', 'circular', 'international_treaty'
            ]);
            $table->enum('status', ['in_force', 'abrogated', 'amended', 'draft'])->default('in_force');
            $table->string('official_number', 50)->nullable();
            $table->string('bo_number', 50)->nullable();
            $table->date('bo_date')->nullable();
            $table->date('promulgation_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->integer('total_articles')->default(0);
            $table->text('pdf_official_url')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes');
    }
};
