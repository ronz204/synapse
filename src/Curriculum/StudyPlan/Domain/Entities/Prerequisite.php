<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Entities;

/**
 * Prerequisite — child entity of the StudyPlan aggregate. A directed edge
 * `requiredCourse -> dependentCourse`, scoped to a single plan. Invariant
 * enforcement (distinct courses, both linked to the owning plan) lives on
 * StudyPlan::addPrerequisite(), which is the only place this is constructed
 * from outside the aggregate — this class stays a plain data holder.
 */
final class Prerequisite
{
    private function __construct(
        private readonly ?int $id,
        private readonly int $requiredCourseId,
        private readonly int $dependentCourseId,
    ) {}

    public static function create(int $requiredCourseId, int $dependentCourseId): self
    {
        return new self(id: null, requiredCourseId: $requiredCourseId, dependentCourseId: $dependentCourseId);
    }

    public static function reconstitute(int $id, int $requiredCourseId, int $dependentCourseId): self
    {
        return new self(id: $id, requiredCourseId: $requiredCourseId, dependentCourseId: $dependentCourseId);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function requiredCourseId(): int
    {
        return $this->requiredCourseId;
    }

    public function dependentCourseId(): int
    {
        return $this->dependentCourseId;
    }
}
