<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Contracts;

use Src\Curriculum\Modality\Domain\Entities\Modality;

/**
 * Port (in the Hexagonal sense) that Infrastructure adapters must
 * implement. The Domain and Application layers depend only on this
 * abstraction — never on Eloquent, the database, or any concrete driver.
 */
interface ModalityRepositoryInterface
{
    public function find(int $id): ?Modality;

    /**
     * Full, unpaginated collection — backs the client-side (Alpine) catalog
     * table, same reasoning as CourseRepositoryInterface::all().
     *
     * @return array<int, Modality>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Modality>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function nameExists(string $name, ?int $excludeId = null): bool;

    public function save(Modality $modality): Modality;

    public function delete(int $id): void;

    /**
     * True when some course currently points at this modality, or some
     * modality_resolutions row references it — both are restrictOnDelete
     * at the database level; this is what lets DeleteModalityUseCase
     * reject with a specific message instead of a raw QueryException.
     */
    public function isInUse(int $modalityId): bool;
}
