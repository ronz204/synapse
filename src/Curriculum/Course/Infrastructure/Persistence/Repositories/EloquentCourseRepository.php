<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Infrastructure\Persistence\Repositories;

use App\Models\Course as CourseModel;
use App\Models\Modality as ModalityModel;
use Illuminate\Database\Eloquent\Collection;
use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Entities\Course;

final class EloquentCourseRepository implements CourseRepositoryInterface
{
    /**
     * Explicit allow-list — $sortBy ultimately reaches raw SQL via orderBy(),
     * and Livewire action arguments are client-controllable.
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['code', 'name'];

    public function find(int $id): ?Course
    {
        $model = CourseModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = CourseModel::query();

        if (filled($search)) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'code';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        /** @var Collection<int, CourseModel> $models */
        $models = $query->orderBy($column, $direction)->get();

        return $models->map($this->toDomain(...))->all();
    }

    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = CourseModel::query();

        if (filled($search)) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'code';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($column, $direction)->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toDomain(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        return CourseModel::query()
            ->where('code', $code)
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->exists();
    }

    public function save(Course $course): Course
    {
        $model = $course->id()
            ? CourseModel::query()->findOrFail($course->id())
            : new CourseModel;

        $model->program_id = $course->programId();
        $model->modality_id = $course->modalityId() ?? $this->defaultModalityId();
        $model->code = $course->code();
        $model->name = $course->name();
        $model->is_service = $course->isService();
        $model->is_bottleneck = $course->isBottleneck();
        $model->requires_laboratory = $course->requiresLaboratory();
        $model->laboratory_type = $course->laboratoryType();
        $model->active = $course->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    /**
     * Resolves the RC-01 default-modality rule ("a course with no modality
     * specified defaults to Presencial") the same way CourseFactory already
     * does, so a course created without picking a modality still gets one.
     * Not a dedicated ModalityRepository port: modality catalog management
     * belongs to the (separate, not-yet-built) RC-03 slice — this is just
     * the one lookup RC-01's own acceptance criteria requires.
     */
    private function defaultModalityId(): int
    {
        return ModalityModel::query()->firstOrCreate(
            ['name' => 'Presencial'],
            ['requires_resolution' => false],
        )->id;
    }

    private function toDomain(CourseModel $model): Course
    {
        return Course::reconstitute(
            id: $model->id,
            programId: $model->program_id,
            modalityId: $model->modality_id,
            code: $model->code,
            name: $model->name,
            isService: $model->is_service,
            isBottleneck: $model->is_bottleneck,
            requiresLaboratory: $model->requires_laboratory,
            laboratoryType: $model->laboratory_type,
            active: $model->active,
        );
    }
}
