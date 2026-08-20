<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Application\UseCases;

use Src\Curriculum\AcademicHistory\Domain\Contracts\AcademicHistoryRepositoryInterface;
use Src\Curriculum\AcademicHistory\Domain\ValueObjects\StudentSummary;

/**
 * Lists the students whose history can be inspected — RC-02b's first input,
 * which until now existed only as seeded rows with no way to consult them.
 */
final class ListStudentsUseCase
{
    public function __construct(
        private readonly AcademicHistoryRepositoryInterface $repository,
    ) {}

    /**
     * @return array{items: array<int, StudentSummary>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->paginateStudents($search, $perPage, $page, $sortBy, $sortDir);
    }
}
