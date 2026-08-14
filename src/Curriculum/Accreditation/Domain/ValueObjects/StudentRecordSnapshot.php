<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Domain\ValueObjects;

use App\Enums\AcademicRecordStatus;

/**
 * A read-only view of one of a student's existing academic records — just
 * enough to decide whether a course is already satisfied one way or another,
 * without the resolver ever needing to know about Eloquent.
 */
final readonly class StudentRecordSnapshot
{
    public function __construct(
        public int $courseId,
        public AcademicRecordStatus $status,
        public ?int $equivalencyId,
    ) {}

    /**
     * True for Passed, accredited by equivalency, or accredited by another
     * kind of validation — the three states the spec lists as already
     * satisfying the course. Deliberately excludes PrerequisiteWaived, which
     * the spec never lists among them.
     */
    public function satisfiesCourse(): bool
    {
        return in_array($this->status, [
            AcademicRecordStatus::Passed,
            AcademicRecordStatus::AccreditedByEquivalency,
            AcademicRecordStatus::AccreditedByValidation,
        ], true);
    }
}
