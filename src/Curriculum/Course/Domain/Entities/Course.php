<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Domain\Entities;

use App\Enums\LaboratoryType;
use Src\Curriculum\Course\Domain\Exceptions\CourseCodeAlreadyExistsException;
use Src\Curriculum\Course\Domain\Exceptions\NonServiceCourseRequiresProgramException;

/**
 * Course — Aggregate Root of the Curriculum bounded context.
 *
 * Pure PHP. Zero framework coupling — no Eloquent, no Illuminate imports.
 * A course belongs to a program, not to a single study plan or level — it
 * is intentionally reusable across multiple plans/levels (see StudyPlan,
 * which references a course only by id). Credits are not a property of a
 * course: they live on the course-to-level link, since the same course can
 * be worth a different number of credits in different levels/plans.
 */
final class Course
{
    private function __construct(
        private readonly ?int $id,
        private ?int $programId,
        private ?int $modalityId,
        private string $code,
        private string $name,
        private bool $isService,
        private bool $isBottleneck,
        private bool $requiresLaboratory,
        private ?LaboratoryType $laboratoryType,
        private bool $active,
    ) {
        $this->assertServiceCourseConsistency();
    }

    public static function create(
        string $code,
        string $name,
        ?int $programId,
        ?int $modalityId = null,
        bool $isService = false,
        bool $isBottleneck = false,
        bool $requiresLaboratory = false,
        ?LaboratoryType $laboratoryType = null,
    ): self {
        return new self(
            id: null,
            programId: $programId,
            modalityId: $modalityId,
            code: $code,
            name: $name,
            isService: $isService,
            isBottleneck: $isBottleneck,
            requiresLaboratory: $requiresLaboratory,
            laboratoryType: $laboratoryType,
            active: true,
        );
    }

    public static function reconstitute(
        int $id,
        ?int $programId,
        ?int $modalityId,
        string $code,
        string $name,
        bool $isService,
        bool $isBottleneck,
        bool $requiresLaboratory,
        ?LaboratoryType $laboratoryType,
        bool $active,
    ): self {
        return new self(
            id: $id,
            programId: $programId,
            modalityId: $modalityId,
            code: $code,
            name: $name,
            isService: $isService,
            isBottleneck: $isBottleneck,
            requiresLaboratory: $requiresLaboratory,
            laboratoryType: $laboratoryType,
            active: $active,
        );
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    /**
     * The caller (a use case) is responsible for asking
     * CourseRepositoryInterface::codeExists() first — same division of
     * responsibility as assertCodeIsAvailable().
     */
    public function changeCode(string $code): void
    {
        $this->code = $code;
    }

    public function updateAttributes(
        bool $isService,
        ?int $programId,
        bool $isBottleneck,
        bool $requiresLaboratory,
        ?LaboratoryType $laboratoryType,
    ): void {
        $this->isService = $isService;
        $this->programId = $programId;
        $this->isBottleneck = $isBottleneck;
        $this->requiresLaboratory = $requiresLaboratory;
        $this->laboratoryType = $laboratoryType;

        $this->assertServiceCourseConsistency();
    }

    public function assignModality(?int $modalityId): void
    {
        $this->modalityId = $modalityId;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * Mirrors the database's chk_courses_service_program check constraint
     * exactly (`is_service = 1 OR program_id IS NOT NULL`): the only
     * combination it rejects is a non-service course with no program. A
     * service course keeping a program is not blocked by the constraint
     * either, so this stays a one-directional check, not an XOR.
     */
    private function assertServiceCourseConsistency(): void
    {
        if (! $this->isService && $this->programId === null) {
            throw NonServiceCourseRequiresProgramException::forCode($this->code);
        }
    }

    /**
     * Pure: takes the already-computed uniqueness result instead of querying
     * anything itself, so it stays testable with no database — the caller
     * (a use case) is responsible for asking
     * CourseRepositoryInterface::codeExists() first.
     */
    public static function assertCodeIsAvailable(bool $codeAlreadyTaken, string $code): void
    {
        if ($codeAlreadyTaken) {
            throw CourseCodeAlreadyExistsException::forCode($code);
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function programId(): ?int
    {
        return $this->programId;
    }

    public function modalityId(): ?int
    {
        return $this->modalityId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isService(): bool
    {
        return $this->isService;
    }

    public function isBottleneck(): bool
    {
        return $this->isBottleneck;
    }

    public function requiresLaboratory(): bool
    {
        return $this->requiresLaboratory;
    }

    public function laboratoryType(): ?LaboratoryType
    {
        return $this->laboratoryType;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
