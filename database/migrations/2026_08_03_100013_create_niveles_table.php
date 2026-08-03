<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_estudio_id')->constrained('planes_estudio')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->timestamps();

            $table->unique(['plan_estudio_id', 'numero'], 'niveles_plan_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles');
    }
};
