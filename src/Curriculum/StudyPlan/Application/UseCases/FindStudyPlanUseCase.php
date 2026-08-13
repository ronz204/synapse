<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\UseCases;

use Src\Curriculum\StudyPlan\Domain\Contracts\StudyPlanRepositoryInterface;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;
use Src\Curriculum\StudyPlan\Domain\Exceptions\StudyPlanNotFoundException;

final class FindStudyPlanUseCase
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $repository,
    ) {}

    public function handle(int $id): StudyPlan
    {
        return $this->repository->find($id) ?? throw StudyPlanNotFoundException::withId($id);
    }
}
