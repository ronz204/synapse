<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\DTOs;

final readonly class CourseLinkDTO
{
    public function __construct(
        public int $courseId,
        public int $credits,
    ) {}
}
