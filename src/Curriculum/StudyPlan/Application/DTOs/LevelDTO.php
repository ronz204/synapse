<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\DTOs;

final readonly class LevelDTO
{
    /**
     * @param  array<int, CourseLinkDTO>  $courseLinks
     */
    public function __construct(
        public int $number,
        public array $courseLinks = [],
    ) {}
}
