<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Infrastructure\Persistence\Repositories;

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Models\Course as CourseModel;
use App\Models\Equivalency as EquivalencyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\Events\EquivalencyBecameActive;
use Src\Curriculum\Equivalency\Domain\Services\DirectionEdgeOrientation;
use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;
use Src\Shared\Document\Contracts\AttachableDocument;
use Src\Shared\Document\Contracts\DocumentRepositoryInterface;
use Src\Shared\Document\Contracts\StoredDocument;

final class EloquentEquivalencyRepository implements EquivalencyRepositoryInterface
{
    /**
     * Explicit allow-list — $sortBy ultimately reaches raw SQL via orderBy(),
     * and Livewire action arguments are client-controllable.
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['resolution_number', 'created_at'];

    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    public function find(int $id): ?Equivalency
    {
        $model = EquivalencyModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = $this->searchable($search);

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'created_at';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        /** @var Collection<int, EquivalencyModel> $models */
        $models = $query->orderBy($column, $direction)->get();

        return $models->map($this->toDomain(...))->all();
    }

    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = $this->searchable($search);

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'created_at';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($column, $direction)->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toDomain(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function courseNode(int $courseId): CourseNode
    {
        $course = CourseModel::query()->findOrFail($courseId, ['id', 'code']);

        return new CourseNode($course->id, $course->code);
    }

    public function activeGraph(): array
    {
        return EquivalencyModel::query()->active()
            ->with(['sourceCourse:id,code', 'targetCourse:id,code'])
            ->get()
            ->flatMap(fn (EquivalencyModel $row) => DirectionEdgeOrientation::edgesFor(
                $row->direction,
                new CourseNode($row->source_course_id, $row->sourceCourse->code),
                new CourseNode($row->target_course_id, $row->targetCourse->code),
                $row->id,
            ))
            ->all();
    }

    public function findActiveForPair(int $sourceCourseId, int $targetCourseId, EquivalencyDirection $direction): ?Equivalency
    {
        $row = EquivalencyModel::query()->active()
            ->where('source_course_id', $sourceCourseId)
            ->where('target_course_id', $targetCourseId)
            ->where('direction', $direction)
            ->first();

        return $row ? $this->toDomain($row) : null;
    }

    public function register(Equivalency $equivalency, AttachableDocument $document): Equivalency
    {
        $result = DB::transaction(function () use ($equivalency, $document): Equivalency {
            // `status` is deliberately not fillable (see the model's own
            // docblock), so a plain create() would silently drop it from the
            // INSERT itself and fall through to the DB's own Vigente
            // default — a save() afterward would be one query too late,
            // since the row lands in the DB as Active either way. forceCreate()
            // bypasses that guard for this one write, the only place allowed
            // to set status directly.
            $row = EquivalencyModel::query()->forceCreate([
                'source_course_id' => $equivalency->sourceCourseId(),
                'target_course_id' => $equivalency->targetCourseId(),
                'direction' => $equivalency->direction(),
                'resolution_number' => $equivalency->resolutionNumber(),
                'status' => EquivalencyStatus::Active,
            ]);

            $this->documents->attach($document, EquivalencyModel::class, $row->id);

            return $this->toDomain($row);
        });

        // Dispatched only after the transaction above has already committed
        // — never inside the closure — so a rollback can never fire it.
        event(new EquivalencyBecameActive($result->id()));

        return $result;
    }

    public function resolveContradiction(Equivalency $candidate, AttachableDocument $candidateDocument, int $existingEquivalencyId, bool $candidatePrevails): Equivalency
    {
        $result = DB::transaction(function () use ($candidate, $candidateDocument, $existingEquivalencyId, $candidatePrevails): Equivalency {
            $existing = EquivalencyModel::query()->findOrFail($existingEquivalencyId);

            // When the candidate prevails, the existing row must be demoted
            // *before* the candidate is inserted as Active: the DB's
            // active_key unique index would otherwise briefly see two Active
            // rows for the same (source, target, direction) triple at the
            // moment of insert. superseded_by_id is filled in afterward,
            // once the candidate's id actually exists.
            if ($candidatePrevails) {
                $existing->status = EquivalencyStatus::Superseded;
                $existing->save();
            }

            $candidateRow = EquivalencyModel::query()->forceCreate([
                'source_course_id' => $candidate->sourceCourseId(),
                'target_course_id' => $candidate->targetCourseId(),
                'direction' => $candidate->direction(),
                'resolution_number' => $candidate->resolutionNumber(),
                'status' => $candidatePrevails ? EquivalencyStatus::Active : EquivalencyStatus::Superseded,
                'superseded_by_id' => $candidatePrevails ? null : $existing->id,
            ]);

            if ($candidatePrevails) {
                $existing->superseded_by_id = $candidateRow->id;
                $existing->save();
            }

            $this->documents->attach($candidateDocument, EquivalencyModel::class, $candidateRow->id);

            return $this->toDomain($candidateRow);
        });

        // Only the candidate winning makes a *new* equivalency Active — if
        // the existing one prevailed, nothing changed that needs sweeping.
        if ($candidatePrevails) {
            event(new EquivalencyBecameActive($result->id()));
        }

        return $result;
    }

    public function findDocument(int $equivalencyId): ?StoredDocument
    {
        return $this->documents->findFor(EquivalencyModel::class, $equivalencyId);
    }

    /**
     * @return Builder<EquivalencyModel>
     */
    private function searchable(?string $search): Builder
    {
        $query = EquivalencyModel::query();

        if (filled($search)) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('resolution_number', 'like', "%{$search}%")
                    ->orWhereHas('sourceCourse', fn ($courseQuery) => $courseQuery->where('code', 'like', "%{$search}%"))
                    ->orWhereHas('targetCourse', fn ($courseQuery) => $courseQuery->where('code', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function toDomain(EquivalencyModel $model): Equivalency
    {
        return Equivalency::reconstitute(
            id: $model->id,
            sourceCourseId: $model->source_course_id,
            targetCourseId: $model->target_course_id,
            direction: $model->direction,
            resolutionNumber: $model->resolution_number,
            status: $model->status,
            supersededById: $model->superseded_by_id,
        );
    }
}
