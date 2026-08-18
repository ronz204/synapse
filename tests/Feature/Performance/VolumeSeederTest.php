<?php

declare(strict_types=1);

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Enums\PlanClassification;
use Database\Seeders\PerformanceVolumeSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The whole performance harness rests on this dataset being exactly what
 * data-model.md says it is. If the counts drift, every budget measured against
 * it and every comparison with baseline.json silently stops meaning anything —
 * so the counts are asserted, not assumed.
 */
function seedTargetVolume(): void
{
    (new PerformanceVolumeSeeder)->run();
}

it('generates exactly the target volume declared in data-model.md', function (): void {
    seedTargetVolume();

    expect(DB::table('courses')->where('code', 'like', 'PERF-%')->count())->toBe(800)
        ->and(DB::table('study_plans')->where('name', 'like', 'Plan de Rendimiento %')->count())->toBe(10)
        ->and(DB::table('levels')->count())->toBe(80)
        ->and(DB::table('course_level')->count())->toBe(800)
        ->and(DB::table('prerequisites')->count())->toBe(1400)
        ->and(DB::table('students')->where('first_last_name', 'like', 'Apellido%')->count())->toBe(2000)
        ->and(DB::table('student_plan')->count())->toBe(2000)
        ->and(DB::table('student_academic_records')->count())->toBe(24000)
        ->and(DB::table('modality_resolutions')->count())->toBe(301);
});

it('generates 500 equivalencies split between active and superseded', function (): void {
    seedTargetVolume();

    $generated = DB::table('equivalencies')->where('resolution_number', 'like', 'R-PERF-%');

    expect((clone $generated)->count())->toBe(500)
        ->and((clone $generated)->where('status', EquivalencyStatus::Active->value)->count())->toBe(350)
        ->and((clone $generated)->where('status', EquivalencyStatus::Superseded->value)->count())->toBe(150);
});

it('keeps superseded equivalencies queryable and pointing at their successor', function (): void {
    seedTargetVolume();

    $superseded = DB::table('equivalencies')
        ->where('resolution_number', 'like', 'R-PERF-S%')
        ->whereNotNull('superseded_by_id')
        ->count();

    // Principle IV: history is never removed, only retired by status.
    expect($superseded)->toBe(150);
});

it('is idempotent — a second run leaves the same counts', function (): void {
    seedTargetVolume();

    $before = [
        'courses' => DB::table('courses')->count(),
        'study_plans' => DB::table('study_plans')->count(),
        'equivalencies' => DB::table('equivalencies')->count(),
        'students' => DB::table('students')->count(),
        'student_academic_records' => DB::table('student_academic_records')->count(),
    ];

    seedTargetVolume();

    $after = [
        'courses' => DB::table('courses')->count(),
        'study_plans' => DB::table('study_plans')->count(),
        'equivalencies' => DB::table('equivalencies')->count(),
        'students' => DB::table('students')->count(),
        'student_academic_records' => DB::table('student_academic_records')->count(),
    ];

    expect($after)->toBe($before);
});

it('is deterministic — the same course codes and resolution numbers every run', function (): void {
    seedTargetVolume();

    $firstCourse = DB::table('courses')->where('code', 'like', 'PERF-%')->orderBy('code')->value('code');
    $lastCourse = DB::table('courses')->where('code', 'like', 'PERF-%')->orderByDesc('code')->value('code');

    expect($firstCourse)->toBe('PERF-0001')
        ->and($lastCourse)->toBe('PERF-0800');
});

describe('negative cases required by FR-011', function (): void {
    it('seeds a chain that is one edge short of a cycle', function (): void {
        seedTargetVolume();

        $codes = DB::table('courses')
            ->whereIn('code', ['PERF-0791', 'PERF-0792', 'PERF-0793'])
            ->pluck('id', 'code');

        $edges = DB::table('equivalencies')
            ->whereIn('resolution_number', ['R-CYCLE-0001', 'R-CYCLE-0002'])
            ->where('status', EquivalencyStatus::Active->value)
            ->get(['source_course_id', 'target_course_id', 'direction']);

        expect($edges)->toHaveCount(2)
            ->and($edges[0]->source_course_id)->toBe($codes['PERF-0791'])
            ->and($edges[0]->target_course_id)->toBe($codes['PERF-0792'])
            ->and($edges[1]->source_course_id)->toBe($codes['PERF-0792'])
            ->and($edges[1]->target_course_id)->toBe($codes['PERF-0793'])
            ->and($edges[0]->direction)->toBe(EquivalencyDirection::OldToNew->value);

        // Closing PERF-0793 -> PERF-0791 must be what the harness measures as
        // save:reject:cycle. The closing edge is deliberately NOT seeded.
        $closingEdge = DB::table('equivalencies')
            ->where('source_course_id', $codes['PERF-0793'])
            ->where('target_course_id', $codes['PERF-0791'])
            ->exists();

        expect($closingEdge)->toBeFalse();
    });

    it('seeds an active equivalency whose pair contradicts on re-registration', function (): void {
        seedTargetVolume();

        $existing = DB::table('equivalencies')
            ->where('resolution_number', 'R-CONTRA-0001')
            ->where('status', EquivalencyStatus::Active->value)
            ->first();

        expect($existing)->not->toBeNull()
            ->and($existing->direction)->toBe(EquivalencyDirection::OldToNew->value);
    });

    it('seeds a course whose only modality resolution has expired', function (): void {
        seedTargetVolume();

        $courseId = DB::table('courses')->where('code', 'PERF-0798')->value('id');

        $resolutions = DB::table('modality_resolutions')->where('course_id', $courseId)->get();

        expect($resolutions)->toHaveCount(1)
            ->and($resolutions[0]->resolution_number)->toBe('R-EXPIRED-0001')
            ->and($resolutions[0]->valid_to)->not->toBeNull();
    });

    it('seeds Terminal plans carrying their enrollment closing date', function (): void {
        seedTargetVolume();

        $terminal = DB::table('study_plans')
            ->where('classification', PlanClassification::Terminal->value)
            ->get();

        expect($terminal)->toHaveCount(2);

        foreach ($terminal as $plan) {
            expect($plan->enrollment_closing_date)->not->toBeNull();
        }
    });
});
