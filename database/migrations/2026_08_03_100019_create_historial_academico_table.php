<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_academico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('periodo_academico_id')->nullable()->constrained('periodos_academicos')->nullOnDelete();
            $table->enum('estado', [
                'Aprobado',
                'Reprobado',
                'Acreditado por equiparación',
                'Acreditado por convalidación',
                'Requisito levantado',
            ]);
            $table->decimal('nota', 5, 2)->nullable();
            $table->foreignId('equiparacion_id')->nullable()->constrained('equiparaciones')->nullOnDelete();
            $table->timestamps();

            $table->index(['estudiante_id', 'curso_id'], 'historial_academico_estudiante_curso_index');
            $table->index(['curso_id', 'estado'], 'historial_academico_curso_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_academico');
    }
};
