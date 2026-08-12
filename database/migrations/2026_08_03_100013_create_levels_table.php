<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained('study_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->timestamps();

            $table->unique(['study_plan_id', 'number'], 'levels_study_plan_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
