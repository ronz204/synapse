<?php

namespace Database\Seeders;

use App\Models\Carrera;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CarreraSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Las 14 carreras del Manual de Atinencias en alcance (siga.sql §9.1).
     */
    public function run(): void
    {
        $carreras = [
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

        foreach ($carreras as $nombre) {
            Carrera::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
