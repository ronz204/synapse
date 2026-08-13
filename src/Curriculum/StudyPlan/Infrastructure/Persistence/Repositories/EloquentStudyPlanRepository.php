<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Infrastructure\Persistence\Repositories;

use App\Models\Level as LevelModel;
use App\Models\Prerequisite as PrerequisiteModel;
use App\Models\StudentPlan as StudentPlanModel;
use App\Models\StudyPlan as StudyPlanModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Curriculum\StudyPlan\Domain\Contracts\StudyPlanRepositoryInterface;
use Src\Curriculum\StudyPlan\Domain\Entities\CourseLink;
use Src\Curriculum\StudyPlan\Domain\Entities\Level;
use Src\Curriculum\StudyPlan\Domain\Entities\Prerequisite;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;

final class EloquentStudyPlanRepository implements StudyPlanRepositoryInterface
{
    /**
     * Explicit allow-list — $sortBy ultimately reaches raw SQL via orderBy().
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name', 'implementation_year', 'classification'];

    public function find(int $id): ?StudyPlan
    {
        $model = StudyPlanModel::query()->with(['levels.courses', 'prerequisites'])->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = StudyPlanModel::query();

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        /** @var Collection<int, StudyPlanModel> $models */
        $models = $query->orderBy($column, $direction)->get();

        return $models->map($this->toDomainSummary(...))->all();
    }

    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = StudyPlanModel::query();

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($column, $direction)->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toDomainSummary(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function save(StudyPlan $studyPlan): StudyPlan
    {
        return DB::transaction(function () use ($studyPlan): StudyPlan {
            $model = $studyPlan->id()
                ? StudyPlanModel::query()->findOrFail($studyPlan->id())
                : new StudyPlanModel;

            $model->program_id = $studyPlan->programId();
            $model->name = $studyPlan->name();
            $model->implementation_year = $studyPlan->implementationYear();
            $model->classification = $studyPlan->classification();
            $model->enrollment_closing_date = $studyPlan->enrollmentClosingDate()
                ? Carbon::instance($studyPlan->enrollmentClosingDate())
                : null;
            $model->save();

            $this->syncLevels($model, $studyPlan->levels());
            $this->replacePrerequisites($model, $studyPlan->prerequisites());

            return $this->toDomain($model->load(['levels.courses', 'prerequisites']));
        });
    }

    public function activeStudentCountsByLevel(int $planId): array
    {
        $rows = StudentPlanModel::query()
            ->join('students', 'students.id', '=', 'student_plan.student_id')
            ->where('student_plan.study_plan_id', $planId)
            ->where('students.active', true)
            ->whereNotNull('student_plan.current_level')
            ->selectRaw('student_plan.current_level as level, count(*) as total')
            ->groupBy('student_plan.current_level')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['level']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Levels are diffed by id: ones no longer present in the domain
     * aggregate are deleted (cascading to their course_level rows via the
     * FK's ON DELETE CASCADE); survivors are updated or created, and each
     * one's course_level pivot is re-synced with its current credits.
     *
     * @param  array<int, Level>  $levels
     */
    private function syncLevels(StudyPlanModel $model, array $levels): void
    {
        $existingIds = LevelModel::query()->where('study_plan_id', $model->id)->pluck('id')->all();
        $survivingIds = array_values(array_filter(array_map(fn (Level $level) => $level->id(), $levels)));

        $toDelete = array_diff($existingIds, $survivingIds);

        if ($toDelete !== []) {
            LevelModel::query()->whereIn('id', $toDelete)->delete();
        }

        foreach ($levels as $level) {
            if ($level->id() === null) {
                $levelModel = new LevelModel([
                    'study_plan_id' => $model->id,
                    'number' => $level->number(),
                ]);
                $levelModel->save();
            } else {
                $levelModel = LevelModel::query()->findOrFail($level->id());
                $levelModel->number = $level->number();
                $levelModel->save();
            }

            $pivotData = [];
            foreach ($level->courseLinks() as $courseLink) {
                $pivotData[$courseLink->courseId] = ['credits' => $courseLink->credits];
            }

            $levelModel->courses()->sync($pivotData);
        }
    }

    /**
     * Prerequisites carry no mutable state beyond their (required, dependent)
     * pair, and StudyPlan::replaceStructure() always rebuilds the whole list
     * from scratch (every domain Prerequisite here has a null id) — a full
     * delete-and-reinsert is simpler than an id-based diff and equally
     * correct.
     *
     * @param  array<int, Prerequisite>  $prerequisites
     */
    private function replacePrerequisites(StudyPlanModel $model, array $prerequisites): void
    {
        PrerequisiteModel::query()->where('study_plan_id', $model->id)->delete();

        if ($prerequisites === []) {
            return;
        }

        $rows = array_map(fn (Prerequisite $prerequisite) => [
            'study_plan_id' => $model->id,
            'required_course_id' => $prerequisite->requiredCourseId(),
            'dependent_course_id' => $prerequisite->dependentCourseId(),
            'created_at' => now(),
        ], $prerequisites);

        PrerequisiteModel::query()->insert($rows);
    }

    /**
     * Full hydration, including levels (with course links) and
     * prerequisites — required whenever the aggregate's own invariants
     * (prerequisite scoping) might be evaluated against it.
     */
    private function toDomain(StudyPlanModel $model): StudyPlan
    {
        $levels = $model->levels->map(function (LevelModel $levelModel): Level {
            $courseLinks = $levelModel->courses->map(
                fn ($course) => new CourseLink($course->id, (int) $course->pivot->credits)
            )->all();

            return Level::reconstitute($levelModel->id, $levelModel->number, $courseLinks);
        })->all();

        $prerequisites = $model->prerequisites->map(
            fn (PrerequisiteModel $prerequisiteModel) => Prerequisite::reconstitute(
                $prerequisiteModel->id,
                $prerequisiteModel->required_course_id,
                $prerequisiteModel->dependent_course_id,
            )
        )->all();

        return StudyPlan::reconstitute(
            id: $model->id,
            programId: $model->program_id,
            name: $model->name,
            implementationYear: (int) $model->implementation_year,
            classification: $model->classification,
            enrollmentClosingDate: $model->enrollment_closing_date?->toDateTimeImmutable(),
            levels: $levels,
            prerequisites: $prerequisites,
        );
    }

    /**
     * Lightweight projection for catalog listings (all()/paginate()): never
     * touches the levels/prerequisites relations, so it can't trigger a lazy
     * load per row — the catalog table only needs the plan's own fields.
     */
    private function toDomainSummary(StudyPlanModel $model): StudyPlan
    {
        return StudyPlan::reconstitute(
            id: $model->id,
            programId: $model->program_id,
            name: $model->name,
            implementationYear: (int) $model->implementation_year,
            classification: $model->classification,
            enrollmentClosingDate: $model->enrollment_closing_date?->toDateTimeImmutable(),
            levels: [],
            prerequisites: [],
        );
    }
}
