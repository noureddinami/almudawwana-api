<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commentary_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('commentary_id');
            $table->uuid('editor_id');
            $table->text('content_ar');
            $table->string('edit_summary', 500)->nullable();
            $table->integer('revision_number');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('commentary_id')->references('id')->on('commentaries')->onDelete('cascade');
            $table->foreign('editor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commentary_revisions');
    }
};
