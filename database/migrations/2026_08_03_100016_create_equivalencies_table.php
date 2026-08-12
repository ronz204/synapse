<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equivalencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('target_course_id')->constrained('courses')->restrictOnDelete();
            $table->enum('direction', ['Anterior a nuevo', 'Nuevo a anterior', 'Bidireccional']);
            $table->string('resolution_number', 60);
            $table->enum('status', ['Vigente', 'Sustituida'])->default('Vigente');
            $table->foreignId('superseded_by_id')->nullable()->constrained('equivalencies')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_course_id', 'target_course_id', 'resolution_number'], 'equivalencies_pair_resolution_unique');
            $table->index('status', 'equivalencies_status_index');
        });

        DB::statement('ALTER TABLE equivalencies ADD CONSTRAINT chk_equivalencies_distinct CHECK (source_course_id <> target_course_id)');

        // DB-level guard against the RC-02 contradiction: a generated column that
        // only has a value when status = 'Vigente'; the unique index on it allows
        // multiple 'Sustituida' rows (NULL) but only one 'Vigente' row per
        // (source_course_id, target_course_id, direction).
        Schema::table('equivalencies', function (Blueprint $table) {
            $table->string('active_key', 64)
                ->nullable()
                ->storedAs("CASE WHEN status = 'Vigente' THEN CONCAT(source_course_id, '-', target_course_id, '-', direction) ELSE NULL END")
                ->unique('equivalencies_active_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equivalencies');
    }
};
