<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Domain\ValueObjects;

use App\Enums\AcademicRecordStatus;

/**
 * One line of a student's simplified internal history: a course and how the
 * student stands on it.
 *
 * `resolutionNumber` is populated only for a course reached through an
 * equivalency — RC-02b requires the accredited course to be readable
 * *together with* the resolution that approved it, so the pair travels as a
 * single value rather than being re-joined at the view.
 */
final readonly class AcademicRecordEntry
{
    public function __construct(
        public string $courseCode,
        public string $courseName,
        public AcademicRecordStatus $status,
        public ?string $resolutionNumber = null,
    ) {}

    /**
     * Whether this line exists because an equivalency accredited the course,
     * as opposed to the student having sat it.
     */
    public function isAccreditedByEquivalency(): bool
    {
        return $this->status === AcademicRecordStatus::AccreditedByEquivalency;
    }
}
