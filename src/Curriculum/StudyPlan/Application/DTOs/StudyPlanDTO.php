<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\DTOs;

use App\Enums\PlanClassification;
use DateTimeImmutable;

/**
 * Immutable data boundary between Presentation and Application layers, for
 * a plan's basic fields only (program, name, year, classification, closing
 * date) — the structure (levels/course links/prerequisites) is a separate
 * boundary, StudyPlanStructureDTO, saved through its own use case.
 */
final readonly class StudyPlanDTO
{
    public function __construct(
        public int $programId,
        public string $name,
        public int $implementationYear,
        public PlanClassification $classification,
        public ?DateTimeImmutable $enrollmentClosingDate,
    ) {}

    /**
     * @param  array{program_id: int, name: string, implementation_year: int, classification: string, enrollment_closing_date?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            programId: $data['program_id'],
            name: $data['name'],
            implementationYear: $data['implementation_year'],
            classification: PlanClassification::from($data['classification']),
            enrollmentClosingDate: isset($data['enrollment_closing_date']) && $data['enrollment_closing_date'] !== null
                ? new DateTimeImmutable($data['enrollment_closing_date'])
                : null,
        );
    }
}
