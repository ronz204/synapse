<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Infrastructure\Persistence\Repositories;

use App\Models\ModalityResolution as ModalityResolutionModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Curriculum\Modality\Application\DTOs\ModalityResolutionDTO;
use Src\Curriculum\Modality\Domain\Contracts\ModalityResolutionRepositoryInterface;
use Src\Curriculum\Modality\Domain\Entities\ModalityResolution;
use Src\Shared\Document\Contracts\DocumentRepositoryInterface;
use Src\Shared\Document\Contracts\StoredDocument;

final class EloquentModalityResolutionRepository implements ModalityResolutionRepositoryInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    /**
     * The resolution row and its attached document are meaningless without
     * each other — same "Wrap Multi-Step Writes in Transactions" rule
     * EloquentEquivalencyRepository::register() already follows. The
     * document's temporary file is only moved to permanent storage here,
     * inside this transaction — never earlier.
     */
    public function create(int $courseId, int $modalityId, ModalityResolutionDTO $dto): ModalityResolution
    {
        return DB::transaction(function () use ($courseId, $modalityId, $dto): ModalityResolution {
            $row = ModalityResolutionModel::query()->create([
                'course_id' => $courseId,
                'modality_id' => $modalityId,
                'resolution_number' => $dto->number,
                'approving_body' => $dto->approvingBody,
                'valid_from' => $dto->validFrom,
                'valid_to' => $dto->validTo,
            ]);

            $this->documents->attach($dto->document, ModalityResolutionModel::class, $row->id);

            return $this->toDomain($row);
        });
    }

    public function allFor(int $courseId, int $modalityId): array
    {
        return ModalityResolutionModel::query()
            ->where('course_id', $courseId)
            ->where('modality_id', $modalityId)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function findDocument(int $modalityResolutionId): ?StoredDocument
    {
        return $this->documents->findFor(ModalityResolutionModel::class, $modalityResolutionId);
    }

    private function toDomain(ModalityResolutionModel $model): ModalityResolution
    {
        return ModalityResolution::reconstitute(
            id: $model->id,
            courseId: $model->course_id,
            modalityId: $model->modality_id,
            number: $model->resolution_number,
            approvingBody: $model->approving_body,
            validFrom: CarbonImmutable::instance($model->valid_from),
            validTo: $model->valid_to !== null ? CarbonImmutable::instance($model->valid_to) : null,
        );
    }
}
