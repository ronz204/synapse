<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The domain enums backing these columns (EquivalencyDirection, EquivalencyStatus,
 * PlanClassification, LaboratoryType, AcademicRecordStatus) originally used their
 * Spanish display label as the backed value, coupling the persisted/matched value
 * to the UI language. This migration moves every one of them to a stable English
 * identifier; display translation now goes through __() against the enum case's
 * ->name in the Blade layer instead.
 *
 * Each column is widened to accept both the old and new value sets, remapped row
 * by row, then narrowed to the new set only — safe to run against an empty
 * (freshly migrated) table or one already holding the old Spanish values.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE courses MODIFY laboratory_type ENUM('Laboratorio de cómputo','Laboratorio de ciencias','Laboratorio de idiomas','computer_lab','science_lab','language_lab') NULL");
        DB::table('courses')->where('laboratory_type', 'Laboratorio de cómputo')->update(['laboratory_type' => 'computer_lab']);
        DB::table('courses')->where('laboratory_type', 'Laboratorio de ciencias')->update(['laboratory_type' => 'science_lab']);
        DB::table('courses')->where('laboratory_type', 'Laboratorio de idiomas')->update(['laboratory_type' => 'language_lab']);
        DB::statement("ALTER TABLE courses MODIFY laboratory_type ENUM('computer_lab','science_lab','language_lab') NULL");

        // MySQL's utf8mb4_unicode_ci collation treats 'Terminal' and 'terminal' as
        // the same ENUM label (error 1291, duplicated value), so the widen step
        // can't hold both at once — route through a 'terminal_tmp' placeholder
        // instead of widening directly to the final label.
        DB::statement('ALTER TABLE study_plans DROP CHECK chk_study_plans_terminal_date');
        DB::statement("ALTER TABLE study_plans MODIFY classification ENUM('Vigente','Terminal','active','terminal_tmp') NOT NULL DEFAULT 'Vigente'");
        DB::table('study_plans')->where('classification', 'Vigente')->update(['classification' => 'active']);
        DB::table('study_plans')->where('classification', 'Terminal')->update(['classification' => 'terminal_tmp']);
        DB::statement("ALTER TABLE study_plans MODIFY classification ENUM('active','terminal_tmp','terminal') NOT NULL DEFAULT 'active'");
        DB::table('study_plans')->where('classification', 'terminal_tmp')->update(['classification' => 'terminal']);
        DB::statement("ALTER TABLE study_plans MODIFY classification ENUM('active','terminal') NOT NULL DEFAULT 'active'");
        DB::statement("ALTER TABLE study_plans ADD CONSTRAINT chk_study_plans_terminal_date CHECK (classification = 'active' OR enrollment_closing_date IS NOT NULL)");

        DB::statement('ALTER TABLE equivalencies DROP INDEX equivalencies_active_unique');
        DB::statement("ALTER TABLE equivalencies MODIFY direction ENUM('Anterior a nuevo','Nuevo a anterior','Bidireccional','old_to_new','new_to_old','bidirectional') NOT NULL");
        DB::statement("ALTER TABLE equivalencies MODIFY status ENUM('Vigente','Sustituida','active','superseded') NOT NULL DEFAULT 'Vigente'");
        DB::table('equivalencies')->where('direction', 'Anterior a nuevo')->update(['direction' => 'old_to_new']);
        DB::table('equivalencies')->where('direction', 'Nuevo a anterior')->update(['direction' => 'new_to_old']);
        DB::table('equivalencies')->where('direction', 'Bidireccional')->update(['direction' => 'bidirectional']);
        DB::table('equivalencies')->where('status', 'Vigente')->update(['status' => 'active']);
        DB::table('equivalencies')->where('status', 'Sustituida')->update(['status' => 'superseded']);
        DB::statement("ALTER TABLE equivalencies MODIFY direction ENUM('old_to_new','new_to_old','bidirectional') NOT NULL");
        DB::statement("ALTER TABLE equivalencies MODIFY status ENUM('active','superseded') NOT NULL DEFAULT 'active'");
        DB::statement("ALTER TABLE equivalencies MODIFY active_key VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN CONCAT(source_course_id, '-', target_course_id, '-', direction) ELSE NULL END) STORED NULL");
        DB::statement('ALTER TABLE equivalencies ADD UNIQUE equivalencies_active_unique (active_key)');

        DB::statement("ALTER TABLE student_academic_records MODIFY status ENUM('Aprobado','Reprobado','Acreditado por equiparación','Acreditado por convalidación','Requisito levantado','passed','failed','accredited_by_equivalency','accredited_by_validation','prerequisite_waived') NOT NULL");
        DB::table('student_academic_records')->where('status', 'Aprobado')->update(['status' => 'passed']);
        DB::table('student_academic_records')->where('status', 'Reprobado')->update(['status' => 'failed']);
        DB::table('student_academic_records')->where('status', 'Acreditado por equiparación')->update(['status' => 'accredited_by_equivalency']);
        DB::table('student_academic_records')->where('status', 'Acreditado por convalidación')->update(['status' => 'accredited_by_validation']);
        DB::table('student_academic_records')->where('status', 'Requisito levantado')->update(['status' => 'prerequisite_waived']);
        DB::statement("ALTER TABLE student_academic_records MODIFY status ENUM('passed','failed','accredited_by_equivalency','accredited_by_validation','prerequisite_waived') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE student_academic_records MODIFY status ENUM('passed','failed','accredited_by_equivalency','accredited_by_validation','prerequisite_waived','Aprobado','Reprobado','Acreditado por equiparación','Acreditado por convalidación','Requisito levantado') NOT NULL");
        DB::table('student_academic_records')->where('status', 'passed')->update(['status' => 'Aprobado']);
        DB::table('student_academic_records')->where('status', 'failed')->update(['status' => 'Reprobado']);
        DB::table('student_academic_records')->where('status', 'accredited_by_equivalency')->update(['status' => 'Acreditado por equiparación']);
        DB::table('student_academic_records')->where('status', 'accredited_by_validation')->update(['status' => 'Acreditado por convalidación']);
        DB::table('student_academic_records')->where('status', 'prerequisite_waived')->update(['status' => 'Requisito levantado']);
        DB::statement("ALTER TABLE student_academic_records MODIFY status ENUM('Aprobado','Reprobado','Acreditado por equiparación','Acreditado por convalidación','Requisito levantado') NOT NULL");

        DB::statement('ALTER TABLE equivalencies DROP INDEX equivalencies_active_unique');
        DB::statement("ALTER TABLE equivalencies MODIFY direction ENUM('old_to_new','new_to_old','bidirectional','Anterior a nuevo','Nuevo a anterior','Bidireccional') NOT NULL");
        DB::statement("ALTER TABLE equivalencies MODIFY status ENUM('active','superseded','Vigente','Sustituida') NOT NULL DEFAULT 'active'");
        DB::table('equivalencies')->where('direction', 'old_to_new')->update(['direction' => 'Anterior a nuevo']);
        DB::table('equivalencies')->where('direction', 'new_to_old')->update(['direction' => 'Nuevo a anterior']);
        DB::table('equivalencies')->where('direction', 'bidirectional')->update(['direction' => 'Bidireccional']);
        DB::table('equivalencies')->where('status', 'active')->update(['status' => 'Vigente']);
        DB::table('equivalencies')->where('status', 'superseded')->update(['status' => 'Sustituida']);
        DB::statement("ALTER TABLE equivalencies MODIFY direction ENUM('Anterior a nuevo','Nuevo a anterior','Bidireccional') NOT NULL");
        DB::statement("ALTER TABLE equivalencies MODIFY status ENUM('Vigente','Sustituida') NOT NULL DEFAULT 'Vigente'");
        DB::statement("ALTER TABLE equivalencies MODIFY active_key VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN status = 'Vigente' THEN CONCAT(source_course_id, '-', target_course_id, '-', direction) ELSE NULL END) STORED NULL");
        DB::statement('ALTER TABLE equivalencies ADD UNIQUE equivalencies_active_unique (active_key)');

        DB::statement('ALTER TABLE study_plans DROP CHECK chk_study_plans_terminal_date');
        DB::statement("ALTER TABLE study_plans MODIFY classification ENUM('active','terminal','Vigente','terminal_tmp') NOT NULL DEFAULT 'active'");
        DB::table('study_plans')->where('classification', 'active')->update(['classification' => 'Vigente']);
        DB::table('study_plans')->where('classification', 'terminal')->update(['classification' => 'terminal_tmp']);
        DB::statement("ALTER TABLE study_plans MODIFY classification ENUM('Vigente','terminal_tmp','Terminal') NOT NULL DEFAULT 'Vigente'");
        DB::table('study_plans')->where('classification', 'terminal_tmp')->update(['classification' => 'Terminal']);
        DB::statement("ALTER TABLE study_plans MODIFY classification ENUM('Vigente','Terminal') NOT NULL DEFAULT 'Vigente'");
        DB::statement("ALTER TABLE study_plans ADD CONSTRAINT chk_study_plans_terminal_date CHECK (classification = 'Vigente' OR enrollment_closing_date IS NOT NULL)");

        DB::statement("ALTER TABLE courses MODIFY laboratory_type ENUM('computer_lab','science_lab','language_lab','Laboratorio de cómputo','Laboratorio de ciencias','Laboratorio de idiomas') NULL");
        DB::table('courses')->where('laboratory_type', 'computer_lab')->update(['laboratory_type' => 'Laboratorio de cómputo']);
        DB::table('courses')->where('laboratory_type', 'science_lab')->update(['laboratory_type' => 'Laboratorio de ciencias']);
        DB::table('courses')->where('laboratory_type', 'language_lab')->update(['laboratory_type' => 'Laboratorio de idiomas']);
        DB::statement("ALTER TABLE courses MODIFY laboratory_type ENUM('Laboratorio de cómputo','Laboratorio de ciencias','Laboratorio de idiomas') NULL");
    }
};
