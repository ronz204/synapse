<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained('study_plans')->cascadeOnDelete();
            $table->foreignId('required_course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('dependent_course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['study_plan_id', 'required_course_id', 'dependent_course_id'], 'prerequisites_plan_pair_unique');
        });

        DB::statement('ALTER TABLE prerequisites ADD CONSTRAINT chk_prerequisites_distinct CHECK (required_course_id <> dependent_course_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('prerequisites');
    }
};
