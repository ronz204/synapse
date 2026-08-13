<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\UseCases;

use Src\Curriculum\StudyPlan\Application\DTOs\StudyPlanDTO;
use Src\Curriculum\StudyPlan\Domain\Contracts\StudyPlanRepositoryInterface;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;
use Src\Curriculum\StudyPlan\Domain\Exceptions\StudyPlanNotFoundException;

/**
 * Updates only a plan's basic fields (program, name, year, classification,
 * closing date) — its structure (levels/course links/prerequisites) is
 * saved separately, through SaveStudyPlanStructureUseCase.
 */
final class UpdateStudyPlanUseCase
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $repository,
    ) {}

    public function handle(int $id, StudyPlanDTO $dto): StudyPlan
    {
        $studyPlan = $this->repository->find($id) ?? throw StudyPlanNotFoundException::withId($id);

        $studyPlan->rename($dto->name);
        $studyPlan->reclassify($dto->classification, $dto->enrollmentClosingDate);

        return $this->repository->save($studyPlan);
    }
}
