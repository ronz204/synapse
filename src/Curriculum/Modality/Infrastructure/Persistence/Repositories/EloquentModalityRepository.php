<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Infrastructure\Persistence\Repositories;

use App\Models\Course as CourseModel;
use App\Models\Modality as ModalityModel;
use App\Models\ModalityResolution as ModalityResolutionModel;
use Illuminate\Database\Eloquent\Collection;
use Src\Curriculum\Modality\Domain\Contracts\ModalityRepositoryInterface;
use Src\Curriculum\Modality\Domain\Entities\Modality;

final class EloquentModalityRepository implements ModalityRepositoryInterface
{
    /**
     * Explicit allow-list — $sortBy ultimately reaches raw SQL via orderBy(),
     * and Livewire action arguments are client-controllable.
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name'];

    public function find(int $id): ?Modality
    {
        $model = ModalityModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = ModalityModel::query();

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        /** @var Collection<int, ModalityModel> $models */
        $models = $query->orderBy($column, $direction)->get();

        return $models->map($this->toDomain(...))->all();
    }

    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = ModalityModel::query();

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($column, $direction)->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toDomain(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        return ModalityModel::query()
            ->where('name', $name)
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->exists();
    }

    public function save(Modality $modality): Modality
    {
        $model = $modality->id()
            ? ModalityModel::query()->findOrFail($modality->id())
            : new ModalityModel;

        $model->name = $modality->name();
        $model->requires_resolution = $modality->requiresResolution();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ModalityModel::query()->findOrFail($id)->delete();
    }

    public function isInUse(int $modalityId): bool
    {
        return CourseModel::query()->where('modality_id', $modalityId)->exists()
            || ModalityResolutionModel::query()->where('modality_id', $modalityId)->exists();
    }

    private function toDomain(ModalityModel $model): Modality
    {
        return Modality::reconstitute(
            id: $model->id,
            name: $model->name,
            requiresResolution: $model->requires_resolution,
        );
    }
}
