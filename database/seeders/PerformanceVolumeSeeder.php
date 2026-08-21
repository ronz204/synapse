<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Modality;
use App\Models\Program;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Institution-scale volume for performance testing (see performance.md) —
 * not demo/showcase data like CurriculumDemoSeeder. Every table this touches
 * is bulk-inserted via chunked DB::table()->insert() calls instead of
 * Model::create() in a loop: at a few thousand rows, per-row Eloquent event
 * dispatch/hydration overhead would make the seeder itself the bottleneck,
 * not the thing this exists to measure.
 *
 * Called from DatabaseSeeder::run() after the demo seeders. Can also be run
 * standalone against an already-seeded database:
 *
 *   php artisan db:seed --class=PerformanceVolumeSeeder
 */
class PerformanceVolumeSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COURSE_COUNT = 3000;

    private const STUDY_PLAN_COUNT = 20;

    private const LEVELS_PER_PLAN = 6;

    private const COURSES_PER_LEVEL = 8;

    private const EQUIVALENCY_COUNT = 1200;

    private const MODALITY_ASSIGNMENT_COUNT = 1000;

    private const CHUNK_SIZE = 500;

    public function run(): void
    {
        $programIds = Program::query()->pluck('id')->all();
        $presencialId = Modality::query()->where('name', 'Presencial')->value('id');

        if ($programIds === [] || $presencialId === null) {
            $this->command->warn('PerformanceVolumeSeeder needs ProgramSeeder and ModalitySeeder run first.');

            return;
        }

        $courseIds = $this->seedCourses($programIds, $presencialId);
        $this->seedStudyPlans($programIds, $courseIds);
        $equivalencyCount = $this->seedEquivalencies($courseIds);
        $assignmentCount = $this->seedModalityAssignments($courseIds);

        $this->command->info(sprintf(
            'Performance volume seeded: %d courses, %d study plans (%d levels each), %d equivalencies, %d modality assignments.',
            count($courseIds),
            self::STUDY_PLAN_COUNT,
            self::LEVELS_PER_PLAN,
            $equivalencyCount,
            $assignmentCount,
        ));
    }

    /**
     * @param  array<int, int>  $programIds
     * @return array<int, int>
     */
    private function seedCourses(array $programIds, int $presencialId): array
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= self::COURSE_COUNT; $i++) {
            // 1 in 10 is a shared service course (no owning program), matching
            // the ratio the check constraint `is_service = 1 OR program_id IS
            // NOT NULL` allows and the demo data's own DEMO-2xx proportion.
            $isService = $i % 10 === 0;

            $rows[] = [
                'program_id' => $isService ? null : $programIds[$i % count($programIds)],
                'modality_id' => $presencialId,
                'code' => sprintf('VOL-%05d', $i),
                'name' => 'Curso de volumen '.$i,
                'is_service' => $isService,
                'is_bottleneck' => $i % 25 === 0,
                'requires_laboratory' => false,
                'laboratory_type' => null,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table('courses')->insert($chunk);
        }

        return Course::query()->where('code', 'like', 'VOL-%')->pluck('id')->all();
    }

    /**
     * @param  array<int, int>  $programIds
     * @param  array<int, int>  $courseIds
     */
    private function seedStudyPlans(array $programIds, array $courseIds): void
    {
        $now = now();
        $planIds = [];

        for ($p = 1; $p <= self::STUDY_PLAN_COUNT; $p++) {
            $planIds[] = DB::table('study_plans')->insertGetId([
                'program_id' => $programIds[$p % count($programIds)],
                'name' => 'Plan de volumen '.$p,
                'implementation_year' => now()->year - ($p % 5),
                'classification' => 'active',
                'enrollment_closing_date' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

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

        foreach (array_chunk($levelRows, self::CHUNK_SIZE) as $chunk) {
            DB::table('levels')->insert($chunk);
        }

        $levelIds = DB::table('levels')->whereIn('study_plan_id', $planIds)->pluck('id')->all();

        $courseCount = count($courseIds);
        $cursor = 0;
        $courseLevelRows = [];

        foreach ($levelIds as $levelId) {
            for ($c = 0; $c < self::COURSES_PER_LEVEL; $c++) {
                $courseLevelRows[] = [
                    'level_id' => $levelId,
                    'course_id' => $courseIds[$cursor % $courseCount],
                    'credits' => random_int(3, 5),
                    'created_at' => $now,
                ];
                $cursor++;
            }
        }

        foreach (array_chunk($courseLevelRows, self::CHUNK_SIZE) as $chunk) {
            DB::table('course_level')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  array<int, int>  $courseIds
     */
    private function seedEquivalencies(array $courseIds): int
    {
        $now = now();
        $directions = ['old_to_new', 'new_to_old', 'bidirectional'];

        $pool = $courseIds;
        shuffle($pool);

        // Consuming the shuffled pool two-at-a-time without replacement means
        // every course appears in at most one pair, so (source, target) is
        // globally unique across the whole batch for free — satisfying both
        // the pair+resolution unique index and the active_key uniqueness
        // (one active row per source/target/direction) with no bookkeeping.
        $count = min(self::EQUIVALENCY_COUNT, intdiv(count($pool), 2));
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'source_course_id' => $pool[$i * 2],
                'target_course_id' => $pool[$i * 2 + 1],
                'direction' => $directions[$i % 3],
                'resolution_number' => sprintf('R-VOL-%05d', $i + 1),
                'status' => 'active',
                'superseded_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table('equivalencies')->insert($chunk);
        }

        return $count;
    }

    /**
     * @param  array<int, int>  $courseIds
     */
    private function seedModalityAssignments(array $courseIds): int
    {
        $virtualId = Modality::query()->where('name', 'Virtual')->value('id');
        $hybridId = Modality::query()->where('name', 'Híbrido')->value('id');

        if ($virtualId === null || $hybridId === null) {
            $this->command->warn('PerformanceVolumeSeeder needs ModalitySeeder run first for modality assignments.');

            return 0;
        }

        $now = now();
        $pool = $courseIds;
        shuffle($pool);
        $selected = array_slice($pool, 0, self::MODALITY_ASSIGNMENT_COUNT);

        $resolutionRows = [];
        $virtualCourseIds = [];
        $hybridCourseIds = [];

        foreach ($selected as $i => $courseId) {
            $modalityId = $i % 2 === 0 ? $virtualId : $hybridId;

            $resolutionRows[] = [
                'course_id' => $courseId,
                'modality_id' => $modalityId,
                'resolution_number' => sprintf('R-VOL-MOD-%05d', $i + 1),
                'approving_body' => 'Consejo Universitario',
                'valid_from' => now()->subYear()->toDateString(),
                'valid_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($modalityId === $virtualId) {
                $virtualCourseIds[] = $courseId;
            } else {
                $hybridCourseIds[] = $courseId;
            }
        }

        foreach (array_chunk($resolutionRows, self::CHUNK_SIZE) as $chunk) {
            DB::table('modality_resolutions')->insert($chunk);
        }

        foreach (array_chunk($virtualCourseIds, self::CHUNK_SIZE) as $chunk) {
            DB::table('courses')->whereIn('id', $chunk)->update(['modality_id' => $virtualId]);
        }

        foreach (array_chunk($hybridCourseIds, self::CHUNK_SIZE) as $chunk) {
            DB::table('courses')->whereIn('id', $chunk)->update(['modality_id' => $hybridId]);
        }

        return count($selected);
    }
}
