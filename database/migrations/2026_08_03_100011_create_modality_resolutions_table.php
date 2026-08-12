<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modality_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('modality_id')->constrained('modalities')->restrictOnDelete();
            $table->string('resolution_number', 60);
            $table->string('approving_body', 120);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'modality_id', 'resolution_number'], 'modality_resolutions_course_modality_number_unique');
            $table->index(['course_id', 'valid_from', 'valid_to'], 'modality_resolutions_course_validity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modality_resolutions');
    }
};
