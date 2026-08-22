<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Domain\Entities;

use Src\Curriculum\AcademicHistory\Domain\ValueObjects\AcademicRecordEntry;
use Src\Curriculum\AcademicHistory\Domain\ValueObjects\StudentSummary;

/**
 * A student's simplified internal academic history — the artifact RC-02b
 * names as its output.
 *
 * Read-only by construction: this entity is the query projection. Passed
 * inputs go through a dedicated application command, and accreditations are
 * written by the Accreditation context, so neither mutation belongs here.
 */
final class StudentAcademicHistory
{
    /**
     * @param  array<int, AcademicRecordEntry>  $entries
     */
    private function __construct(
        private readonly StudentSummary $student,
        private readonly array $entries,
    ) {}

    /**
     * @param  array<int, AcademicRecordEntry>  $entries
     */
    public static function reconstitute(StudentSummary $student, array $entries): self
    {
        return new self(student: $student, entries: $entries);
    }

    public function student(): StudentSummary
    {
        return $this->student;
    }

    /**
     * @return array<int, AcademicRecordEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Every course this student holds by way of an equivalency. Surfaced as
     * a count on the history header so RC-02b's outcome is visible without
     * reading the whole table row by row.
     *
     * @return array<int, AcademicRecordEntry>
     */
    public function accreditedByEquivalency(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (AcademicRecordEntry $entry): bool => $entry->isAccreditedByEquivalency(),
        ));
    }
}
