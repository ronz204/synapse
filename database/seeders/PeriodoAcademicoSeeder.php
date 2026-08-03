<?php

namespace Database\Seeders;

use App\Models\PeriodoAcademico;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PeriodoAcademicoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Período de la oferta analizada (siga.sql §9.3).
     */
    public function run(): void
    {
        PeriodoAcademico::query()->firstOrCreate(
            ['anio' => 2025, 'cuatrimestre' => 3],
            ['fecha_inicio' => '2025-09-01', 'fecha_fin' => '2025-12-19'],
        );
    }
}
