<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AcademicRecordStatus;
use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Enums\PlanClassification;
use App\Models\AcademicPeriod;
use App\Models\Modality;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Target-volume dataset for the performance harness (feature
 * 002-perceived-performance).
 *
 * This is NOT the demo seeder. CurriculumDemoSeeder and friends exist to
 * exercise the UI by hand and say so themselves: running them twice doubles
 * their data. That behaviour is wrong for measurement, where two runs must
 * leave the database in the same state or a comparison against the baseline
 * means nothing.
 *
 * Three properties this seeder guarantees, all of them load-bearing:
 *
 * 1. DETERMINISTIC. Every value is derived from its index, never from faker.
 *    Same run, same rows, same ids order — so `perf:measure` can be compared
 *    across executions and against specs/002-perceived-performance/baseline.json.
 * 2. IDEMPOTENT. Everything it creates carries the PERF- marker; a second run
 *    detects the marker and stops. Re-seeding from scratch means
 *    `php artisan migrate:fresh` first.
 * 3. NEGATIVE CASES INCLUDED. Volume alone would only ever measure the happy
 *    path. FR-011 requires rejections to be as fast as successes, so the
 *    dataset ships the four domain rejections the harness has to measure:
 *    a chain one edge short of a cycle, an active equivalency whose pair
 *    contradicts on re-registration, a course whose only modality resolution
 *    has expired, and a Terminal plan with its closing date.
 *
 * Volume produced (see specs/002-perceived-performance/data-model.md):
 *   2 programs · 10 study plans · 80 levels · 800 courses · 800 course links
 *   1.200 prerequisites · 5 modalities · 500 equivalencies (350 active,
 *   150 superseded) · 300 modality resolutions · 2.000 students
 *   24.000 academic records
 *
 *   php artisan db:seed --class=PerformanceVolumeSeeder
 */
class PerformanceVolumeSeeder extends Seeder
{
    /** Prefix marking every row this seeder owns, used for the idempotency check. */
    private const MARKER = 'PERF-';

    private const COURSE_COUNT = 800;

    private const PLAN_COUNT = 10;

    private const LEVELS_PER_PLAN = 8;

    private const COURSES_PER_LEVEL = 10;

    private const ACTIVE_EQUIVALENCIES = 350;

    private const SUPERSEDED_EQUIVALENCIES = 150;

    private const MODALITY_RESOLUTIONS = 300;

    private const STUDENT_COUNT = 2000;

    private const RECORDS_PER_STUDENT = 12;

    /**
     * Courses reserved at the end of the catalog for the negative cases, so
     * they can never collide with the generated equivalency pairs above.
     */
    private const CYCLE_CHAIN_CODES = ['PERF-0791', 'PERF-0792', 'PERF-0793'];

    private const CONTRADICTION_CODES = ['PERF-0795', 'PERF-0796'];

    private const EXPIRED_MODALITY_CODE = 'PERF-0798';

    public function run(): void
    {
        // A second run is a deliberate no-op, not an error: the marker is
        // already there. `db:seed` still reports the seeder as DONE, just in
        // milliseconds instead of seconds, which is the signal that nothing
        // happened. To rebuild from scratch, `php artisan migrate:fresh` first.
        if ($this->alreadySeeded()) {
            return;
        }

        $programIds = $this->resolvePrograms();
        $modalityIds = $this->resolveModalities();
        $periodIds = $this->resolveAcademicPeriods();

        $courseIds = $this->seedCourses($programIds, $modalityIds['Presencial']);
        $planIds = $this->seedStudyPlans($programIds);
        $planCourses = $this->seedLevelsAndCourseLinks($planIds, $courseIds);

        $this->seedPrerequisites($planIds, $planCourses);
        $this->seedEquivalencies($courseIds);
        $this->seedModalityResolutions($courseIds, $modalityIds);
        $this->seedStudents($planIds, $planCourses, $periodIds);

        $this->seedNegativeCases($courseIds, $planIds, $modalityIds);

    }

    private function alreadySeeded(): bool
    {
        return DB::table('courses')->where('code', 'like', self::MARKER.'%')->exists();
    }

    /**
     * Reuses whatever active programs already exist so the dataset sits on top
     * of ProgramSeeder rather than competing with it; only tops up if fewer
     * than two are present.
     *
     * @return array<int, int>
     */
    private function resolvePrograms(): array
    {
        $existing = Program::query()->active()->orderBy('id')->take(2)->pluck('id')->all();

        for ($i = count($existing); $i < 2; $i++) {
            $existing[] = Program::query()->create([
                'name' => 'Programa de Rendimiento '.($i + 1),
                'active' => true,
            ])->id;
        }

        return $existing;
    }

    /**
     * @return array<string, int>
     */
    private function resolveModalities(): array
    {
        $definitions = [
            'Presencial' => false,
            'Virtual' => true,
            'Híbrida' => true,
            'Bimodal' => true,
            'A distancia' => true,
        ];

        $ids = [];

        foreach ($definitions as $name => $requiresResolution) {
            $ids[$name] = Modality::query()->firstOrCreate(
                ['name' => $name],
                ['requires_resolution' => $requiresResolution],
            )->id;
        }

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    private function resolveAcademicPeriods(): array
    {
        $ids = AcademicPeriod::query()->orderBy('id')->pluck('id')->all();

        if ($ids !== []) {
            return $ids;
        }

        foreach ([[2024, 1], [2024, 2], [2025, 1], [2025, 2]] as [$year, $quarter]) {
            $ids[] = AcademicPeriod::query()->create([
                'year' => $year,
                'quarter' => $quarter,
                'start_date' => sprintf('%d-%02d-01', $year, $quarter * 4 - 3),
                'end_date' => sprintf('%d-%02d-28', $year, $quarter * 4),
            ])->id;
        }

        return $ids;
    }

    /**
     * @param  array<int, int>  $programIds
     * @return array<int, int> Course ids, index 0 = PERF-0001
     */
    private function seedCourses(array $programIds, int $presencialId): array
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= self::COURSE_COUNT; $i++) {
            $rows[] = [
                'program_id' => $programIds[$i % 2],
                'modality_id' => $presencialId,
                'code' => sprintf('%s%04d', self::MARKER, $i),
                'name' => sprintf('Curso de Rendimiento %04d', $i),
                'is_service' => false,
                'is_bottleneck' => $i % 40 === 0,
                'requires_laboratory' => false,
                'laboratory_type' => null,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('courses', $rows);

        return DB::table('courses')
            ->where('code', 'like', self::MARKER.'%')
            ->orderBy('code')
            ->pluck('id')
            ->all();
    }

    /**
     * Eight plans Vigente, two Terminal with a closing date — the Terminal pair
     * is one of the negative cases the harness needs (FR-011).
     *
     * @param  array<int, int>  $programIds
     * @return array<int, int>
     */
    private function seedStudyPlans(array $programIds): array
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= self::PLAN_COUNT; $i++) {
            $isTerminal = $i > self::PLAN_COUNT - 2;

            $rows[] = [
                'program_id' => $programIds[$i % 2],
                'name' => sprintf('Plan de Rendimiento %02d', $i),
                'implementation_year' => 2015 + $i,
                'classification' => $isTerminal
                    ? PlanClassification::Terminal->value
                    : PlanClassification::Active->value,
                'enrollment_closing_date' => $isTerminal ? '2027-12-31' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('study_plans', $rows);

        return DB::table('study_plans')
            ->where('name', 'like', 'Plan de Rendimiento %')
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    /**
     * Each plan gets 8 levels of 10 courses. Plans draw from overlapping
     * windows of the catalog so a course belongs to more than one plan, which
     * is what the real data looks like and what makes the structure view's
     * queries realistic.
     *
     * @param  array<int, int>  $planIds
     * @param  array<int, int>  $courseIds
     * @return array<int, array<int, int>> planId => list of its course ids, in level order
     */
    private function seedLevelsAndCourseLinks(array $planIds, array $courseIds): array
    {
        $now = now();
        $levelRows = [];

        foreach ($planIds as $planId) {
            for ($number = 1; $number <= self::LEVELS_PER_PLAN; $number++) {
                $levelRows[] = [
                    'study_plan_id' => $planId,
                    'number' => $number,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunked('levels', $levelRows);

        $levelsByPlan = DB::table('levels')
            ->whereIn('study_plan_id', $planIds)
            ->orderBy('study_plan_id')
            ->orderBy('number')
            ->get(['id', 'study_plan_id', 'number'])
            ->groupBy('study_plan_id');

        $coursesPerPlan = self::LEVELS_PER_PLAN * self::COURSES_PER_LEVEL;
        $linkRows = [];
        $planCourses = [];

        foreach (array_values($planIds) as $planIndex => $planId) {
            $offset = $planIndex * $coursesPerPlan % (self::COURSE_COUNT - $coursesPerPlan + 1);
            $planCourses[$planId] = [];

            foreach ($levelsByPlan[$planId] as $levelIndex => $level) {
                for ($slot = 0; $slot < self::COURSES_PER_LEVEL; $slot++) {
                    $courseId = $courseIds[$offset + $levelIndex * self::COURSES_PER_LEVEL + $slot];

                    $linkRows[] = [
                        'level_id' => $level->id,
                        'course_id' => $courseId,
                        'credits' => 3 + $slot % 3,
                        'created_at' => $now,
                    ];

                    $planCourses[$planId][] = $courseId;
                }
            }
        }

        $this->insertChunked('course_level', $linkRows);

        return $planCourses;
    }

    /**
     * Every course in level n+1 depends on two courses from level n — the shape
     * a real curriculum has, and enough of them (1.200) to make the structure
     * view's rendering honest.
     *
     * @param  array<int, int>  $planIds
     * @param  array<int, array<int, int>>  $planCourses
     */
    private function seedPrerequisites(array $planIds, array $planCourses): void
    {
        $now = now();
        $rows = [];

        foreach ($planIds as $planId) {
            $courses = $planCourses[$planId];

            for ($level = 1; $level < self::LEVELS_PER_PLAN; $level++) {
                $previousStart = ($level - 1) * self::COURSES_PER_LEVEL;
                $currentStart = $level * self::COURSES_PER_LEVEL;

                for ($slot = 0; $slot < self::COURSES_PER_LEVEL; $slot++) {
                    $dependent = $courses[$currentStart + $slot];

                    foreach ([$slot, ($slot + 1) % self::COURSES_PER_LEVEL] as $offset) {
                        $required = $courses[$previousStart + $offset];

                        if ($required === $dependent) {
                            continue;
                        }

                        $rows[] = [
                            'study_plan_id' => $planId,
                            'required_course_id' => $required,
                            'dependent_course_id' => $dependent,
                            'created_at' => $now,
                        ];
                    }
                }
            }
        }

        $this->insertChunked('prerequisites', $rows);
    }

    /**
     * 350 active plus 150 superseded. Pairs are drawn from disjoint windows of
     * the catalog (sources 0-349, targets 400-749) so no generated triple can
     * collide with the equivalencies_active_unique index, and so the reserved
     * negative-case courses at 790+ stay untouched.
     *
     * @param  array<int, int>  $courseIds
     */
    private function seedEquivalencies(array $courseIds): void
    {
        $now = now();
        $directions = EquivalencyDirection::cases();
        $activeRows = [];

        for ($i = 0; $i < self::ACTIVE_EQUIVALENCIES; $i++) {
            $activeRows[] = [
                'source_course_id' => $courseIds[$i],
                'target_course_id' => $courseIds[400 + $i],
                'direction' => $directions[$i % 3]->value,
                'resolution_number' => sprintf('R-PERF-A%04d', $i + 1),
                'status' => EquivalencyStatus::Active->value,
                'superseded_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('equivalencies', $activeRows);

        $activeIds = DB::table('equivalencies')
            ->where('resolution_number', 'like', 'R-PERF-A%')
            ->orderBy('resolution_number')
            ->pluck('id')
            ->all();

        // Superseded rows reuse an active pair with a different resolution
        // number: active_key is NULL while status is Sustituida, so the unique
        // index does not apply and the history stays queryable (Principle IV).
        $supersededRows = [];

        for ($i = 0; $i < self::SUPERSEDED_EQUIVALENCIES; $i++) {
            $supersededRows[] = [
                'source_course_id' => $courseIds[$i],
                'target_course_id' => $courseIds[400 + $i],
                'direction' => $directions[$i % 3]->value,
                'resolution_number' => sprintf('R-PERF-S%04d', $i + 1),
                'status' => EquivalencyStatus::Superseded->value,
                'superseded_by_id' => $activeIds[$i],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('equivalencies', $supersededRows);
    }

    /**
     * @param  array<int, int>  $courseIds
     * @param  array<string, int>  $modalityIds
     */
    private function seedModalityResolutions(array $courseIds, array $modalityIds): void
    {
        $now = now();
        $withResolution = array_values(array_filter(
            $modalityIds,
            static fn (string $name) => $name !== 'Presencial',
            ARRAY_FILTER_USE_KEY,
        ));

        $rows = [];

        for ($i = 0; $i < self::MODALITY_RESOLUTIONS; $i++) {
            $rows[] = [
                'course_id' => $courseIds[$i],
                'modality_id' => $withResolution[$i % count($withResolution)],
                'resolution_number' => sprintf('R-MOD-%04d', $i + 1),
                'approving_body' => 'Consejo Universitario',
                'valid_from' => '2024-01-01',
                'valid_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('modality_resolutions', $rows);
    }

    /**
     * @param  array<int, int>  $planIds
     * @param  array<int, array<int, int>>  $planCourses
     * @param  array<int, int>  $periodIds
     */
    private function seedStudents(array $planIds, array $planCourses, array $periodIds): void
    {
        $now = now();
        $studentRows = [];

        for ($i = 1; $i <= self::STUDENT_COUNT; $i++) {
            $studentRows[] = [
                'user_id' => null,
                'national_id' => sprintf('7-%04d-%04d', intdiv($i, 10000), $i % 10000),
                'first_name' => 'Estudiante',
                'first_last_name' => sprintf('Apellido%04d', $i),
                'second_last_name' => sprintf('Segundo%04d', $i),
                'active' => $i % 20 !== 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('students', $studentRows);

        // Matched on the generated surname, not on national_id: StudentFactory
        // builds ids with numerify('#-####-####') and could legitimately produce
        // one starting with 7, which would drag demo students into this set.
        $studentIds = DB::table('students')
            ->where('first_last_name', 'like', 'Apellido%')
            ->orderBy('national_id')
            ->pluck('id')
            ->all();

        $enrollmentRows = [];
        $recordRows = [];

        foreach ($studentIds as $index => $studentId) {
            $planId = $planIds[$index % count($planIds)];
            $courses = $planCourses[$planId];

            $enrollmentRows[] = [
                'student_id' => $studentId,
                'study_plan_id' => $planId,
                'current_level' => $index % self::LEVELS_PER_PLAN + 1,
                'created_at' => $now,
            ];

            for ($r = 0; $r < self::RECORDS_PER_STUDENT; $r++) {
                $recordRows[] = [
                    'student_id' => $studentId,
                    'course_id' => $courses[($index + $r * 7) % count($courses)],
                    'academic_period_id' => $periodIds[$r % count($periodIds)],
                    'status' => AcademicRecordStatus::Passed->value,
                    'grade' => 70 + ($index + $r) % 30,
                    'equivalency_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunked('student_plan', $enrollmentRows);
        $this->insertChunked('student_academic_records', $recordRows);
    }

    /**
     * The four domain rejections the harness is required to measure. Without
     * these, every `save:*` measurement would only cover the happy path and
     * FR-011 ("a rejection is never slower than a success") could not be
     * verified at all.
     *
     * What each one sets up:
     *
     * - CYCLE: PERF-0791 -> PERF-0792 -> PERF-0793, both edges Anterior a
     *   nuevo and Vigente. Registering PERF-0793 -> PERF-0791 in the same
     *   direction closes the loop and must be rejected with the full chain.
     * - CONTRADICTION: PERF-0795 -> PERF-0796 active under R-CONTRA-0001.
     *   Registering the same triple under a different resolution number must
     *   force an explicit human decision.
     * - EXPIRED MODALITY: PERF-0798 has exactly one modality resolution and it
     *   lapsed in 2023, so assigning that modality must be rejected.
     * - TERMINAL PLAN: already covered — the last two plans from
     *   seedStudyPlans() are Terminal and carry a closing date.
     *
     * @param  array<int, int>  $courseIds
     * @param  array<int, int>  $planIds
     * @param  array<string, int>  $modalityIds
     */
    private function seedNegativeCases(array $courseIds, array $planIds, array $modalityIds): void
    {
        $now = now();

        $byCode = DB::table('courses')
            ->whereIn('code', [...self::CYCLE_CHAIN_CODES, ...self::CONTRADICTION_CODES, self::EXPIRED_MODALITY_CODE])
            ->pluck('id', 'code');

        [$first, $second, $third] = self::CYCLE_CHAIN_CODES;

        DB::table('equivalencies')->insert([
            [
                'source_course_id' => $byCode[$first],
                'target_course_id' => $byCode[$second],
                'direction' => EquivalencyDirection::OldToNew->value,
                'resolution_number' => 'R-CYCLE-0001',
                'status' => EquivalencyStatus::Active->value,
                'superseded_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'source_course_id' => $byCode[$second],
                'target_course_id' => $byCode[$third],
                'direction' => EquivalencyDirection::OldToNew->value,
                'resolution_number' => 'R-CYCLE-0002',
                'status' => EquivalencyStatus::Active->value,
                'superseded_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        [$contradictionSource, $contradictionTarget] = self::CONTRADICTION_CODES;

        DB::table('equivalencies')->insert([
            'source_course_id' => $byCode[$contradictionSource],
            'target_course_id' => $byCode[$contradictionTarget],
            'direction' => EquivalencyDirection::OldToNew->value,
            'resolution_number' => 'R-CONTRA-0001',
            'status' => EquivalencyStatus::Active->value,
            'superseded_by_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('modality_resolutions')->insert([
            'course_id' => $byCode[self::EXPIRED_MODALITY_CODE],
            'modality_id' => $modalityIds['Virtual'],
            'resolution_number' => 'R-EXPIRED-0001',
            'approving_body' => 'Consejo Universitario',
            'valid_from' => '2022-01-01',
            'valid_to' => '2023-12-31',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Bulk insert in chunks. Factories would be the idiomatic choice for a
     * handful of rows, but 24.000 academic records one model at a time turns a
     * seconds-long seed into a minutes-long one, and nothing here needs model
     * events or casting.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows, int $chunkSize = 1000): void
    {
        foreach (array_chunk($rows, max(1, $chunkSize)) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
