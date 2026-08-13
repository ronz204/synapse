<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\UseCases;

use Src\Curriculum\StudyPlan\Application\DTOs\StudyPlanDTO;
use Src\Curriculum\StudyPlan\Domain\Contracts\StudyPlanRepositoryInterface;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;

final class CreateStudyPlanUseCase
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $repository,
    ) {}

    public function handle(StudyPlanDTO $dto): StudyPlan
    {
        $studyPlan = StudyPlan::create(
            programId: $dto->programId,
            name: $dto->name,
            implementationYear: $dto->implementationYear,
            classification: $dto->classification,
            enrollmentClosingDate: $dto->enrollmentClosingDate,
        );

        return $this->repository->save($studyPlan);
    }
}
