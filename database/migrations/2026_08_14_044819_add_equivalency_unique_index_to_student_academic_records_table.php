<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `equivalency_id` is nullable, and NULL is never equal to another NULL
     * for uniqueness purposes — so this only ever actually constrains
     * accreditation rows (which always carry an equivalency_id), never a
     * plain Passed/Failed record.
     */
    public function up(): void
    {
        Schema::table('student_academic_records', function (Blueprint $table): void {
            $table->unique(['student_id', 'course_id', 'equivalency_id'], 'student_academic_records_equivalency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_records', function (Blueprint $table): void {
            $table->dropUnique('student_academic_records_equivalency_unique');
        });
    }
};
