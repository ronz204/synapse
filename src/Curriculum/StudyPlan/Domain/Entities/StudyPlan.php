<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Entities;

use App\Enums\PlanClassification;
use DateTimeImmutable;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteCourseNotLinkedToPlanException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteCoursesMustDifferException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteRequiredCourseMustBeInEarlierLevelException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\TerminalPlanRequiresClosingDateException;

/**
 * StudyPlan — Aggregate Root of the Curriculum bounded context.
 *
 * Pure PHP. Zero framework coupling — no Eloquent, no Illuminate imports.
 * Owns Level and Prerequisite as child entities within its own boundary
 * (both are genuinely exclusive to one plan, unlike Course, which is its
 * own aggregate referenced only by id).
 */
final class StudyPlan
{
    /**
     * @param  array<int, Level>  $levels
     * @param  array<int, Prerequisite>  $prerequisites
     */
    private function __construct(
        private readonly ?int $id,
        private int $programId,
        private string $name,
        private int $implementationYear,
        private PlanClassification $classification,
        private ?DateTimeImmutable $enrollmentClosingDate,
        private array $levels,
        private array $prerequisites,
    ) {
        $this->assertClosingDateRule();
    }

    public static function create(
        int $programId,
        string $name,
        int $implementationYear,
        PlanClassification $classification,
        ?DateTimeImmutable $enrollmentClosingDate,
    ): self {
        return new self(
            id: null,
            programId: $programId,
            name: $name,
            implementationYear: $implementationYear,
            classification: $classification,
            enrollmentClosingDate: $enrollmentClosingDate,
            levels: [],
            prerequisites: [],
        );
    }

    /**
     * @param  array<int, Level>  $levels
     * @param  array<int, Prerequisite>  $prerequisites
     */
    public static function reconstitute(
        int $id,
        int $programId,
        string $name,
        int $implementationYear,
        PlanClassification $classification,
        ?DateTimeImmutable $enrollmentClosingDate,
        array $levels,
        array $prerequisites,
    ): self {
        return new self(
            id: $id,
            programId: $programId,
            name: $name,
            implementationYear: $implementationYear,
            classification: $classification,
            enrollmentClosingDate: $enrollmentClosingDate,
            levels: $levels,
            prerequisites: $prerequisites,
        );
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function relocateToProgram(int $programId): void
    {
        $this->programId = $programId;
    }

    public function changeImplementationYear(int $implementationYear): void
    {
        $this->implementationYear = $implementationYear;
    }

    public function reclassify(PlanClassification $classification, ?DateTimeImmutable $enrollmentClosingDate): void
    {
        $this->classification = $classification;
        $this->enrollmentClosingDate = $enrollmentClosingDate;

        $this->assertClosingDateRule();
    }

    /**
     * Terminal-requires-closing-date: mirrors the database's
     * chk_study_plans_terminal_date check constraint.
     */
    private function assertClosingDateRule(): void
    {
        if ($this->classification === PlanClassification::Terminal && $this->enrollmentClosingDate === null) {
            throw TerminalPlanRequiresClosingDateException::forPlan($this->name);
        }
    }

    /**
     * Replaces the whole structure (levels + prerequisites) as one unit.
     * Prerequisites are always revalidated against the *new* level set,
     * never against a stale one — the plan-structure screen always submits
     * the full tree in one save, so there is no partial/incremental state
     * this could go stale against.
     *
     * @param  array<int, Level>  $levels
     * @param  array<int, array{required: int, dependent: int}>  $prerequisitePairs
     */
    public function replaceStructure(array $levels, array $prerequisitePairs): void
    {
        $this->levels = $levels;
        $this->prerequisites = [];

        foreach ($prerequisitePairs as $pair) {
            $this->addPrerequisite($pair['required'], $pair['dependent']);
        }
    }

    /**
     * A prerequisite is scoped to *this* plan: both courses must be linked
     * to some level of this specific plan, not merely exist somewhere in
     * the system. Because Course is reusable across plans, this check is
     * always evaluated against this instance's own levels — never cached,
     * never assumed from another plan.
     *
     * The required course must also belong to a strictly earlier level than
     * the dependent course's — a student must be able to have already
     * passed it by the time the dependent course's level is reached. This
     * also rules out mutual/cyclic prerequisites by construction: any cycle
     * would require a level number to both strictly increase and return to
     * its starting value along the chain, which is impossible.
     */
    public function addPrerequisite(int $requiredCourseId, int $dependentCourseId): void
    {
        if ($requiredCourseId === $dependentCourseId) {
            throw PrerequisiteCoursesMustDifferException::forCourse($requiredCourseId);
        }

        $requiredLevelNumber = $this->levelNumberForCourse($requiredCourseId);
        $dependentLevelNumber = $this->levelNumberForCourse($dependentCourseId);

        if ($requiredLevelNumber === null || $dependentLevelNumber === null) {
            throw PrerequisiteCourseNotLinkedToPlanException::forCourses($requiredCourseId, $dependentCourseId);
        }

        if ($requiredLevelNumber >= $dependentLevelNumber) {
            throw PrerequisiteRequiredCourseMustBeInEarlierLevelException::forCourses(
                $requiredCourseId,
                $dependentCourseId,
                $requiredLevelNumber,
                $dependentLevelNumber,
            );
        }

        $this->prerequisites[] = Prerequisite::create($requiredCourseId, $dependentCourseId);
    }

    private function levelNumberForCourse(int $courseId): ?int
    {
        foreach ($this->levels as $level) {
            if ($level->linksCourse($courseId)) {
                return $level->number();
            }
        }

        return null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function programId(): int
    {
        return $this->programId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function implementationYear(): int
    {
        return $this->implementationYear;
    }

    public function classification(): PlanClassification
    {
        return $this->classification;
    }

    public function enrollmentClosingDate(): ?DateTimeImmutable
    {
        return $this->enrollmentClosingDate;
    }

    /**
     * @return array<int, Level>
     */
    public function levels(): array
    {
        return $this->levels;
    }

    /**
     * @return array<int, Prerequisite>
     */
    public function prerequisites(): array
    {
        return $this->prerequisites;
    }
}
