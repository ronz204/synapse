<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resoluciones_modalidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('modalidad_id')->constrained('modalidades')->restrictOnDelete();
            $table->string('numero_resolucion', 60);
            $table->string('organo_aprobador', 120);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fin')->nullable();
            $table->timestamps();

            $table->unique(['curso_id', 'modalidad_id', 'numero_resolucion'], 'resoluciones_modalidad_curso_modalidad_numero_unique');
            $table->index(['curso_id', 'vigencia_inicio', 'vigencia_fin'], 'resoluciones_modalidad_curso_vigencia_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resoluciones_modalidad');
    }
};
