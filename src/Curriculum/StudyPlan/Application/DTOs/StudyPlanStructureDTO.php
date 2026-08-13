<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\DTOs;

/**
 * The plan-structure screen always submits the whole tree in one save —
 * see StudyPlan::replaceStructure() for why that's a deliberate design
 * choice, not an incidental one.
 */
final readonly class StudyPlanStructureDTO
{
    /**
     * @param  array<int, LevelDTO>  $levels
     * @param  array<int, PrerequisiteDTO>  $prerequisites
     */
    public function __construct(
        public array $levels,
        public array $prerequisites,
    ) {}
}
