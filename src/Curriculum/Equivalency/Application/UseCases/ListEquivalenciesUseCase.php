<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Application\UseCases;

use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;

final class ListEquivalenciesUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $repository,
    ) {}

    /**
     * Full, unpaginated collection — used by client-side (Alpine) tables
     * that resolve search/sort/pagination in the browser with no further
     * round-trip to the server.
     *
     * @return array<int, Equivalency>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->all($search, $sortBy, $sortDir);
    }

    /**
     * @return array{items: array<int, Equivalency>, total: int}
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        int $page = 1,
        ?string $sortBy = null,
        string $sortDir = 'asc',
    ): array {
        return $this->repository->paginate($search, $perPage, $page, $sortBy, $sortDir);
    }
}
