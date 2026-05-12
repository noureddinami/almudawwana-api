<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisprudence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('case_number', 100);
            $table->date('decision_date');
            $table->enum('court_type', [
                'court_of_cassation', 'court_of_appeal', 'first_instance',
                'administrative', 'commercial', 'constitutional'
            ]);
            $table->string('court_name', 255)->nullable();
            $table->string('title_ar', 500)->nullable();
            $table->text('summary_ar')->nullable();
            $table->longText('full_text_ar')->nullable();
            $table->text('pdf_url')->nullable();
            $table->string('source', 255)->nullable();
            $table->timestamps();

            $table->index('decision_date');
            $table->index('court_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisprudence');
    }
};
