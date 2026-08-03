<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_nivel', function (Blueprint $table) {
            $table->foreignId('nivel_id')->constrained('niveles')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->unsignedTinyInteger('creditos');
            $table->timestamp('created_at')->nullable();

            $table->primary(['nivel_id', 'curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_nivel');
    }
};
