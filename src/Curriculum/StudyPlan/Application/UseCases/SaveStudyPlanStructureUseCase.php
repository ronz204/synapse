<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Application\UseCases;

use Src\Curriculum\StudyPlan\Application\DTOs\StudyPlanStructureDTO;
use Src\Curriculum\StudyPlan\Domain\Contracts\StudyPlanRepositoryInterface;
use Src\Curriculum\StudyPlan\Domain\Entities\CourseLink;
use Src\Curriculum\StudyPlan\Domain\Entities\Level;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;
use Src\Curriculum\StudyPlan\Domain\Exceptions\LevelHasActiveStudentsException;
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

        $this->assertNoRemovedLevelHasActiveStudents($planId, $studyPlan->levels(), $levels);

        $prerequisitePairs = array_map(
            fn ($prerequisiteDto) => ['required' => $prerequisiteDto->requiredCourseId, 'dependent' => $prerequisiteDto->dependentCourseId],
            $dto->prerequisites,
        );

        $studyPlan->replaceStructure($levels, $prerequisitePairs);

        return $this->repository->save($studyPlan);
    }

    /**
     * student_plan.current_level is a plain integer, not a foreign key to
     * levels.id (see StudyPlanRepositoryInterface::activeStudentCountsByLevel())
     * — nothing clears or reassigns it when a level disappears. Dropping a
     * level that active students currently sit on would silently orphan
     * them: still counted in student_plan, but invisible from every
     * per-level view afterward, since none of them render a badge for a
     * level number that no longer exists. Block the save instead, the same
     * way every other structural invariant here is enforced.
     *
     * @param  array<int, Level>  $currentLevels
     * @param  array<int, Level>  $newLevels
     */
    private function assertNoRemovedLevelHasActiveStudents(int $planId, array $currentLevels, array $newLevels): void
    {
        $newNumbers = array_map(fn (Level $level) => $level->number(), $newLevels);
        $removedNumbers = array_diff(
            array_map(fn (Level $level) => $level->number(), $currentLevels),
            $newNumbers,
        );

        if ($removedNumbers === []) {
            return;
        }

        $activeCounts = $this->repository->activeStudentCountsByLevel($planId);

        foreach ($removedNumbers as $removedNumber) {
            $count = $activeCounts[$removedNumber] ?? 0;

            if ($count > 0) {
                throw LevelHasActiveStudentsException::forLevel($removedNumber, $count);
            }
        }
    }
}
