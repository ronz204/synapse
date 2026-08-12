<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained('programs')->restrictOnDelete();
            // Course's current modality pointer. No numeric default in the DB —
            // CourseFactory resolves "Presencial" explicitly by name.
            $table->foreignId('modality_id')->nullable()->constrained('modalities')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->boolean('is_service')->default(false);
            $table->boolean('is_bottleneck')->default(false);
            $table->boolean('requires_laboratory')->default(false);
            $table->enum('laboratory_type', [
                'Laboratorio de cómputo',
                'Laboratorio de ciencias',
                'Laboratorio de idiomas',
            ])->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_service_program CHECK (is_service = 1 OR program_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
