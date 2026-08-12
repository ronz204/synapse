<?php

namespace Database\Seeders;

use App\Models\Modality;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ModalitySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Master modality catalog (siga.sql §9.3c). Presencial is the default
     * (RC-03) and must be inserted first.
     */
    public function run(): void
    {
        $modalities = [
            ['name' => 'Presencial', 'requires_resolution' => false],
            ['name' => 'Híbrido', 'requires_resolution' => true],
            ['name' => 'Virtual', 'requires_resolution' => true],
            ['name' => 'Tutoría', 'requires_resolution' => true],
            ['name' => 'Aprendizaje Remoto', 'requires_resolution' => true],
        ];

        foreach ($modalities as $modality) {
            Modality::query()->firstOrCreate(
                ['name' => $modality['name']],
                ['requires_resolution' => $modality['requires_resolution']],
            );
        }
    }
}
