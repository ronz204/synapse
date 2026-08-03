<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_id')->nullable()->constrained('carreras')->restrictOnDelete();
            // Puntero de modalidad actual del curso. Sin default numérico en
            // BD — CursoFactory resuelve "Presencial" explícitamente por nombre.
            $table->foreignId('modalidad_id')->nullable()->constrained('modalidades')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            $table->boolean('es_servicio')->default(false);
            $table->boolean('es_cuello_botella')->default(false);
            $table->boolean('requiere_laboratorio')->default(false);
            $table->enum('tipo_laboratorio', [
                'Laboratorio de cómputo',
                'Laboratorio de ciencias',
                'Laboratorio de idiomas',
            ])->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE cursos ADD CONSTRAINT chk_cursos_servicio_carrera CHECK (es_servicio = 1 OR carrera_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
