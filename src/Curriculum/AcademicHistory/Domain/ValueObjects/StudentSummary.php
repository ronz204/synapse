<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Domain\ValueObjects;

/**
 * Identifies the student a history belongs to, carrying only what the
 * listing and the history header actually display.
 *
 * Deliberately not the Student aggregate: this slice never writes to a
 * student, so depending on the full entity would widen the surface for no
 * gain. `fullName` is composed by the adapter rather than stored, since the
 * three name columns are a persistence detail.
 */
final readonly class StudentSummary
{
    public function __construct(
        public int $id,
        public string $nationalId,
        public string $fullName,
        public bool $active,
    ) {}
}
