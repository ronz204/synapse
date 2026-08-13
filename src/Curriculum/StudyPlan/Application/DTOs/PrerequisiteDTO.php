<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\DTOs;

final readonly class PrerequisiteDTO
{
    public function __construct(
        public int $requiredCourseId,
        public int $dependentCourseId,
    ) {}
}
