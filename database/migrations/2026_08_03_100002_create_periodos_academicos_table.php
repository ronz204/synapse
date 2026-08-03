<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_academicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('cuatrimestre')->comment('1, 2 o 3');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestamps();

            $table->unique(['anio', 'cuatrimestre'], 'periodos_academicos_anio_cuatrimestre_unique');
        });

        DB::statement('ALTER TABLE periodos_academicos ADD CONSTRAINT chk_periodos_cuatrimestre CHECK (cuatrimestre BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_academicos');
    }
};
