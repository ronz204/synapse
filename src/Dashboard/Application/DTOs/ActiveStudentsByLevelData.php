<?php

declare(strict_types=1);

namespace Src\Dashboard\Application\DTOs;

final readonly class ActiveStudentsByLevelData
{
    public function __construct(
        public int $studyPlanId,
        public string $studyPlan,
        public string $program,
        public int $level,
        public int $activeStudents,
    ) {}
}
