<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter')->comment('1, 2 or 3');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['year', 'quarter'], 'academic_periods_year_quarter_unique');
        });

        DB::statement('ALTER TABLE academic_periods ADD CONSTRAINT chk_academic_periods_quarter CHECK (quarter BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
