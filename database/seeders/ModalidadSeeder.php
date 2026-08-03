<?php

namespace Database\Seeders;

use App\Models\Modalidad;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ModalidadSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Catálogo maestro de modalidades (siga.sql §9.3c). Presencial es el
     * default (RC-03) y debe insertarse primero.
     */
    public function run(): void
    {
        $modalidades = [
            ['nombre' => 'Presencial', 'requiere_resolucion' => false],
            ['nombre' => 'Híbrido', 'requiere_resolucion' => true],
            ['nombre' => 'Virtual', 'requiere_resolucion' => true],
            ['nombre' => 'Tutoría', 'requiere_resolucion' => true],
            ['nombre' => 'Aprendizaje Remoto', 'requiere_resolucion' => true],
        ];

        foreach ($modalidades as $modalidad) {
            Modalidad::query()->firstOrCreate(
                ['nombre' => $modalidad['nombre']],
                ['requiere_resolucion' => $modalidad['requiere_resolucion']],
            );
        }
    }
}
