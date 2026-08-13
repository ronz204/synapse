<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\UseCases;

use Src\Curriculum\StudyPlan\Domain\Contracts\StudyPlanRepositoryInterface;

final class GetActiveStudentCountsUseCase
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $repository,
    ) {}

    /**
     * @return array<int, int> keyed by level number
     */
    public function handle(int $planId): array
    {
        return $this->repository->activeStudentCountsByLevel($planId);
    }
}
