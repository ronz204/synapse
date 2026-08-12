<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('national_id', 12)->unique();
            $table->string('first_name', 60);
            $table->string('first_last_name', 60);
            $table->string('second_last_name', 60)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['first_last_name', 'second_last_name'], 'students_last_names_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
