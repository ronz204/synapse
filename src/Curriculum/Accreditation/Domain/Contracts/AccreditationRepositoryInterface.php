<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Domain\Contracts;

use Src\Curriculum\Accreditation\Domain\ValueObjects\StudentRecordSnapshot;

/**
 * Port (in the Hexagonal sense) that Infrastructure adapters must
 * implement. The Domain and Application layers depend only on this
 * abstraction — never on Eloquent, the database, or any concrete driver.
 */
interface AccreditationRepositoryInterface
{
    /**
     * @return array<int, int> ids of students holding a Passed record for this course
     */
    public function studentIdsWithPassedRecordFor(int $courseId): array;

    /**
     * Every academic record the student currently holds, across all
     * courses — the full state needed to decide "already satisfied" and
     * "already granted" for any target course.
     *
     * @return array<int, StudentRecordSnapshot>
     */
    public function recordsFor(int $studentId): array;

    /**
     * Writes the accreditation record. A no-op if a concurrent write already
     * granted this exact student/course/equivalency combination.
     */
    public function grant(int $studentId, int $courseId, int $equivalencyId): void;
}
