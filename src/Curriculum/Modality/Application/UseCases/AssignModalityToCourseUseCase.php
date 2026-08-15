<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\UseCases;

use Carbon\CarbonImmutable;
use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Entities\Course;
use Src\Curriculum\Course\Domain\Exceptions\CourseNotFoundException;
use Src\Curriculum\Modality\Application\DTOs\ModalityResolutionDTO;
use Src\Curriculum\Modality\Domain\Contracts\ModalityRepositoryInterface;
use Src\Curriculum\Modality\Domain\Contracts\ModalityResolutionRepositoryInterface;
use Src\Curriculum\Modality\Domain\Entities\ModalityResolution;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityNotFoundException;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityResolutionDocumentRequiredException;
use Src\Curriculum\Modality\Domain\Exceptions\NoValidModalityResolutionException;
use Src\Curriculum\Modality\Domain\Services\ModalityAssignmentEligibility;

/**
 * The write-time gate at the center of RC-03: a course can be assigned a
 * modality only when either it doesn't require a resolution, or a
 * currently-valid one is on file for that course/modality pair — reusing
 * one already on file, filing a fresh one in the same submission, or both.
 *
 * Orchestrates two contexts (Course's own repository port as a read+write
 * dependency, Modality/ModalityResolution as this context's own) — the
 * same cross-context orchestration pattern RC-02b's
 * GrantAccreditationsForPassedCourseUseCase already established.
 */
final class AssignModalityToCourseUseCase
{
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
        private readonly ModalityRepositoryInterface $modalities,
        private readonly ModalityResolutionRepositoryInterface $resolutions,
        private readonly ModalityAssignmentEligibility $eligibility,
    ) {}

    public function handle(int $courseId, int $modalityId, ?ModalityResolutionDTO $newResolution): Course
    {
        $course = $this->courses->find($courseId) ?? throw CourseNotFoundException::withId($courseId);
        $modality = $this->modalities->find($modalityId) ?? throw ModalityNotFoundException::forId($modalityId);

        if ($newResolution !== null) {
            if ($newResolution->document === null) {
                throw ModalityResolutionDocumentRequiredException::missing();
            }

            $this->resolutions->create($courseId, $modalityId, $newResolution);
        }

        $windows = array_map(
            static fn (ModalityResolution $resolution): array => [
                'validFrom' => $resolution->validFrom(),
                'validTo' => $resolution->validTo(),
            ],
            $this->resolutions->allFor($courseId, $modalityId),
        );

        if (! $this->eligibility->isSatisfied($modality->requiresResolution(), $windows, CarbonImmutable::now())) {
            throw NoValidModalityResolutionException::forCourse($courseId);
        }

        $course->assignModality($modalityId);

        return $this->courses->save($course);
    }
}
