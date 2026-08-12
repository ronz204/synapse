<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->enum('status', [
                'Aprobado',
                'Reprobado',
                'Acreditado por equiparación',
                'Acreditado por convalidación',
                'Requisito levantado',
            ]);
            $table->decimal('grade', 5, 2)->nullable();
            $table->foreignId('equivalency_id')->nullable()->constrained('equivalencies')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'course_id'], 'student_academic_records_student_course_index');
            $table->index(['course_id', 'status'], 'student_academic_records_course_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_records');
    }
};
