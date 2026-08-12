<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AcademicPeriodSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The period covered by the analyzed offering (siga.sql §9.3).
     */
    public function run(): void
    {
        AcademicPeriod::query()->firstOrCreate(
            ['year' => 2025, 'quarter' => 3],
            ['start_date' => '2025-09-01', 'end_date' => '2025-12-19'],
        );
    }
}
