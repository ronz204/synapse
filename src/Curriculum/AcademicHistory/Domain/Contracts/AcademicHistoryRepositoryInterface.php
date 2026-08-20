<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Domain\Contracts;

use Src\Curriculum\AcademicHistory\Domain\Entities\StudentAcademicHistory;
use Src\Curriculum\AcademicHistory\Domain\ValueObjects\StudentSummary;

/**
 * The port this slice depends on. Read-only on purpose: the history is
 * written by the Accreditation context, and offering a save() here would
 * hand callers a second, unguarded way to alter records that RC-02b's
 * invariants are enforced on.
 *
 * Only `paginateStudents()` is offered for the listing — no `all()` — because
 * the student table is the largest in the system and loading it whole into
 * the browser, the way the client-mode CRUD screens do, does not scale here.
 */
interface AcademicHistoryRepositoryInterface
{
    /**
     * @return array{items: array<int, StudentSummary>, total: int}
     */
    public function paginateStudents(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function findHistory(int $studentId): ?StudentAcademicHistory;
}
