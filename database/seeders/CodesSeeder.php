<?php

namespace Database\Seeders;

use App\Models\Code;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CodesSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // ── PDFs disponibles localement ──────────────────
            [
                'slug'              => 'code-penal',
                'title_ar'          => 'مجموعة القانون الجنائي',
                'title_fr'          => 'Code pénal',
                'description_ar'    => 'مجموعة القانون الجنائي المغربي المصادق عليها بالظهير الشريف رقم 1.59.413',
                'type'              => 'code',
                'status'            => 'in_force',
                'official_number'   => '1.59.413',
                'promulgation_date' => '1962-11-26',
            ],
            [
                'slug'              => 'code-procedure-penale',
                'title_ar'          => 'قانون المسطرة الجنائية',
                'title_fr'          => 'Code de procédure pénale',
                'description_ar'    => 'القانون رقم 22.01 المتعلق بالمسطرة الجنائية بتنفيذ الظهير الشريف رقم 1.02.255',
                'type'              => 'code',
                'status'            => 'in_force',
                'official_number'   => '22.01',
                'promulgation_date' => '2003-01-03',
            ],
            [
                'slug'              => 'loi-peines-alternatives',
                'title_ar'          => 'القانون المتعلق بالعقوبات البديلة',
                'title_fr'          => 'Loi relative aux peines alternatives',
                'description_ar'    => 'القانون رقم 43.22 المتعلق بالعقوبات البديلة بتنفيذ الظهير الشريف رقم 1.24.32',
                'type'              => 'ordinary_law',
                'status'            => 'in_force',
                'official_number'   => '43.22',
                'promulgation_date' => '2024-06-13',
            ],

            // ── Autres codes prioritaires du briefing ─────────
            [
                'slug'              => 'code-obligations-contrats',
                'title_ar'          => 'قانون الالتزامات والعقود',
                'title_fr'          => 'Code des obligations et des contrats (DOC)',
                'description_ar'    => 'قانون الالتزامات والعقود الصادر بظهير 12 غشت 1913',
                'type'              => 'code',
                'status'            => 'in_force',
                'official_number'   => '12/08/1913',
                'promulgation_date' => '1913-08-12',
            ],
            [
                'slug'              => 'code-travail',
                'title_ar'          => 'مدونة الشغل',
                'title_fr'          => 'Code du travail',
                'description_ar'    => 'القانون رقم 65.99 المتعلق بمدونة الشغل',
                'type'              => 'code',
                'status'            => 'in_force',
                'official_number'   => '65.99',
                'promulgation_date' => '2003-09-11',
            ],
            [
                'slug'              => 'code-famille',
                'title_ar'          => 'مدونة الأسرة',
                'title_fr'          => 'Code de la famille (Moudawwana)',
                'description_ar'    => 'القانون رقم 70.03 بمثابة مدونة الأسرة',
                'type'              => 'code',
                'status'            => 'in_force',
                'official_number'   => '70.03',
                'promulgation_date' => '2004-02-03',
            ],
            [
                'slug'              => 'code-commerce',
                'title_ar'          => 'مدونة التجارة',
                'title_fr'          => 'Code de commerce',
                'description_ar'    => 'القانون رقم 15.95 المتعلق بمدونة التجارة',
                'type'              => 'code',
                'status'            => 'in_force',
                'official_number'   => '15.95',
                'promulgation_date' => '1996-08-01',
            ],
            [
                'slug'              => 'code-procedure-civile',
                'title_ar'          => 'قانون المسطرة المدنية',
                'title_fr'          => 'Code de procédure civile',
                'description_ar'    => 'قانون المسطرة المدنية',
                'type'              => 'code',
                'status'            => 'in_force',
                'official_number'   => '1.74.447',
                'promulgation_date' => '1974-09-28',
            ],
        ];

        foreach ($codes as $data) {
            Code::firstOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['id' => Str::uuid()])
            );
        }

        $this->command->info('✅ ' . count($codes) . ' codes juridiques insérés.');
    }
}
