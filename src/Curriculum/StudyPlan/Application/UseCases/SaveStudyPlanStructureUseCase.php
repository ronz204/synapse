<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\UseCases;

use Src\Curriculum\StudyPlan\Application\DTOs\StudyPlanStructureDTO;
use Src\Curriculum\StudyPlan\Domain\Contracts\StudyPlanRepositoryInterface;
use Src\Curriculum\StudyPlan\Domain\Entities\CourseLink;
use Src\Curriculum\StudyPlan\Domain\Entities\Level;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;
use Src\Curriculum\StudyPlan\Domain\Exceptions\StudyPlanNotFoundException;

final class SaveStudyPlanStructureUseCase
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $repository,
    ) {}

    public function handle(int $planId, StudyPlanStructureDTO $dto): StudyPlan
    {
        $studyPlan = $this->repository->find($planId) ?? throw StudyPlanNotFoundException::withId($planId);

        $levels = array_map(
            fn ($levelDto) => Level::create(
                number: $levelDto->number,
                courseLinks: array_map(
                    fn ($linkDto) => new CourseLink($linkDto->courseId, $linkDto->credits),
                    $levelDto->courseLinks,
                ),
            ),
            $dto->levels,
        );

        $prerequisitePairs = array_map(
            fn ($prerequisiteDto) => ['required' => $prerequisiteDto->requiredCourseId, 'dependent' => $prerequisiteDto->dependentCourseId],
            $dto->prerequisites,
        );

        $studyPlan->replaceStructure($levels, $prerequisitePairs);

        return $this->repository->save($studyPlan);
    }
}
