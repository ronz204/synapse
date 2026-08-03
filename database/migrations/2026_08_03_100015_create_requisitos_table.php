<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_estudio_id')->constrained('planes_estudio')->cascadeOnDelete();
            $table->foreignId('curso_requerido_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('curso_exige_id')->constrained('cursos')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['plan_estudio_id', 'curso_requerido_id', 'curso_exige_id'], 'requisitos_plan_par_unique');
        });

        DB::statement('ALTER TABLE requisitos ADD CONSTRAINT chk_requisitos_distintos CHECK (curso_requerido_id <> curso_exige_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos');
    }
};
