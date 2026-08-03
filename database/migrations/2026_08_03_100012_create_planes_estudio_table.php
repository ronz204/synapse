<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_estudio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->year('anio_implementacion');
            $table->enum('clasificacion', ['Vigente', 'Terminal'])->default('Vigente');
            $table->date('fecha_cierre_matricula')->nullable();
            $table->timestamps();

            $table->unique(['carrera_id', 'nombre'], 'planes_estudio_carrera_nombre_unique');
            $table->index('clasificacion', 'planes_estudio_clasificacion_index');
        });

        DB::statement("ALTER TABLE planes_estudio ADD CONSTRAINT chk_planes_terminal_fecha CHECK (clasificacion = 'Vigente' OR fecha_cierre_matricula IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_estudio');
    }
};
