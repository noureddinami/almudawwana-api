<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name_ar', 100);
            $table->string('name_fr', 100)->nullable();
            $table->string('color', 20)->default('slate'); // blue, teal, violet, amber, green, slate, red
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the existing types
        DB::table('code_types')->insert([
            ['slug' => 'constitution',  'name_ar' => 'دستور',              'name_fr' => 'Constitution',           'color' => 'amber',  'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'organic_law',   'name_ar' => 'قانون تنظيمي',       'name_fr' => 'Loi organique',          'color' => 'violet', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'ordinary_law',  'name_ar' => 'قانون',              'name_fr' => 'Loi ordinaire',          'color' => 'teal',   'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'code',          'name_ar' => 'مدونة',              'name_fr' => 'Code',                   'color' => 'blue',   'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'decree_law',    'name_ar' => 'مرسوم بقانون',       'name_fr' => 'Décret-loi',             'color' => 'slate',  'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'decree',        'name_ar' => 'مرسوم',              'name_fr' => 'Décret',                 'color' => 'slate',  'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'order',         'name_ar' => 'قرار وزيري',         'name_fr' => 'Arrêté ministériel',     'color' => 'green',  'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'circular',      'name_ar' => 'منشور',              'name_fr' => 'Circulaire',             'color' => 'slate',  'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'international_treaty', 'name_ar' => 'معاهدة دولية','name_fr' => 'Traité international',  'color' => 'red',    'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Change codes.type from ENUM to VARCHAR
        Schema::table('codes', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_types');

        Schema::table('codes', function (Blueprint $table) {
            $table->enum('type', [
                'constitution', 'organic_law', 'ordinary_law', 'code',
                'decree_law', 'decree', 'order', 'circular', 'international_treaty'
            ])->change();
        });
    }
};
