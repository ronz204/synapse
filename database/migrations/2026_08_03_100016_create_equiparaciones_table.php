<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equiparaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_origen_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('curso_destino_id')->constrained('cursos')->restrictOnDelete();
            $table->enum('sentido', ['Anterior a nuevo', 'Nuevo a anterior', 'Bidireccional']);
            $table->string('numero_resolucion', 60);
            $table->enum('estado', ['Vigente', 'Sustituida'])->default('Vigente');
            $table->foreignId('sustituida_por_id')->nullable()->constrained('equiparaciones')->nullOnDelete();
            $table->timestamps();

            $table->unique(['curso_origen_id', 'curso_destino_id', 'numero_resolucion'], 'equiparaciones_par_resolucion_unique');
            $table->index('estado', 'equiparaciones_estado_index');
        });

        DB::statement('ALTER TABLE equiparaciones ADD CONSTRAINT chk_equiparaciones_distintos CHECK (curso_origen_id <> curso_destino_id)');

        // Blindaje a nivel de BD contra la contradicción de RC-02: columna
        // generada que solo tiene valor cuando estado = 'Vigente'; el índice
        // único sobre ella permite múltiples filas "Sustituida" (NULL) pero
        // solo una "Vigente" por (curso_origen_id, curso_destino_id, sentido).
        Schema::table('equiparaciones', function (Blueprint $table) {
            $table->string('vigente_key', 64)
                ->nullable()
                ->storedAs("CASE WHEN estado = 'Vigente' THEN CONCAT(curso_origen_id, '-', curso_destino_id, '-', sentido) ELSE NULL END")
                ->unique('equiparaciones_vigente_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equiparaciones');
    }
};
