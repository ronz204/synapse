<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Domain\Contracts;

use Src\Curriculum\Course\Domain\Entities\Course;

/**
 * Port (in the Hexagonal sense) that Infrastructure adapters must
 * implement. The Domain and Application layers depend only on this
 * abstraction — never on Eloquent, the database, or any concrete driver.
 */
interface CourseRepositoryInterface
{
    public function find(int $id): ?Course;

    /**
     * Full, unpaginated collection — backs client-side (Alpine) tables that
     * resolve search/sort/pagination in the browser. Reserved for datasets
     * small enough to ship to the client in one response.
     *
     * @return array<int, Course>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Course>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * True when some other course already uses this code. $excludeId lets an
     * update ignore the course's own current row.
     */
    public function codeExists(string $code, ?int $excludeId = null): bool;

    public function save(Course $course): Course;
}
