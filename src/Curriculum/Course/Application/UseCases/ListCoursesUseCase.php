<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Application\UseCases;

use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Entities\Course;

final class ListCoursesUseCase
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    /**
     * Full, unpaginated collection — used by client-side (Alpine) tables
     * that resolve search/sort/pagination in the browser with no further
     * round-trip to the server.
     *
     * @return array<int, Course>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->all($search, $sortBy, $sortDir);
    }

    /**
     * @return array{items: array<int, Course>, total: int}
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
