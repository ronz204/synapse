<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('cedula', 12)->unique();
            $table->string('nombre', 60);
            $table->string('primer_apellido', 60);
            $table->string('segundo_apellido', 60)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['primer_apellido', 'segundo_apellido'], 'estudiantes_apellidos_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiantes');
    }
};
