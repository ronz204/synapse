<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The 14 programs from the Manual de Atinencias in scope (siga.sql §9.1).
     */
    public function run(): void
    {
        $programs = [
            'Administración y Gestión de Recursos Humanos',
            'Administración Aduanera',
            'Ingeniería en Tecnologías de Información - Tecnologías de Información',
            'Ingeniería del Software - Tecnologías Informáticas',
            'Contabilidad y Finanzas - Contaduría Pública',
            'Asistencia Administrativa',
            'Inglés como Lengua Extranjera',
            'Administración Agroindustrial',
            'Gestión de Centros de Servicios Compartidos',
            'Ingeniería en Mantenimiento Agroindustrial Sostenible - Mantenimiento Agroindustrial Sostenible',
            'Ingeniería en Gestión Ambiental',
            'Ingeniería en Salud Ocupacional y Ambiente - Salud Ocupacional',
            'Ingeniería en Tecnología de Alimentos - Tecnología de Alimentos',
            'Administración del Comercio Exterior',
        ];

        foreach ($programs as $name) {
            Program::query()->firstOrCreate(['name' => $name]);
        }
    }
}
