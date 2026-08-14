<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Application\UseCases;

use Src\Curriculum\Accreditation\Domain\Contracts\AccreditationRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Services\DirectionEdgeOrientation;
use Src\Curriculum\Equivalency\Domain\ValueObjects\EquivalencyEdge;

/**
 * Orchestrates the "equivalency just became active" trigger: finds which
 * course(s) now act as a qualifying source for this one equivalency, sweeps
 * students who already hold a Passed record on each, and delegates the
 * actual accreditation decision to the same core operation the "student
 * just passed a course" trigger uses — never a separate copy of that logic.
 */
final class SweepNewlyActiveEquivalencyForQualifyingStudentsUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $equivalencies,
        private readonly AccreditationRepositoryInterface $accreditations,
        private readonly GrantAccreditationsForPassedCourseUseCase $grantForPassedCourse,
    ) {}

    public function handle(int $equivalencyId): void
    {
        $equivalency = $this->equivalencies->find($equivalencyId);

        if ($equivalency === null) {
            return;
        }

        $source = $this->equivalencies->courseNode($equivalency->sourceCourseId());
        $target = $this->equivalencies->courseNode($equivalency->targetCourseId());

        $edges = DirectionEdgeOrientation::edgesFor($equivalency->direction(), $source, $target, $equivalencyId);

        $fromCourseIds = array_unique(array_map(
            fn (EquivalencyEdge $edge): int => $edge->from->id,
            $edges,
        ));

        foreach ($fromCourseIds as $fromCourseId) {
            foreach ($this->accreditations->studentIdsWithPassedRecordFor($fromCourseId) as $studentId) {
                $this->grantForPassedCourse->handle($studentId, $fromCourseId);
            }
        }
    }
}
