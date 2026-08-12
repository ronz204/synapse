<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_plan', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('study_plan_id')->constrained('study_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('current_level')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->primary(['student_id', 'study_plan_id']);
            $table->index(['study_plan_id', 'current_level'], 'student_plan_plan_level_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_plan');
    }
};
