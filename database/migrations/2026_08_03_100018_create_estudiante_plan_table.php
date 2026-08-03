<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiante_plan', function (Blueprint $table) {
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('plan_estudio_id')->constrained('planes_estudio')->cascadeOnDelete();
            $table->unsignedTinyInteger('nivel_actual')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->primary(['estudiante_id', 'plan_estudio_id']);
            $table->index(['plan_estudio_id', 'nivel_actual'], 'estudiante_plan_plan_nivel_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_plan');
    }
};
