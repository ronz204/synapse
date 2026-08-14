<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Application\UseCases;

use Src\Curriculum\Accreditation\Domain\Contracts\AccreditationRepositoryInterface;
use Src\Curriculum\Accreditation\Domain\Services\AccreditationResolver;
use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;

/**
 * The single underlying accreditation operation both triggers described by
 * the spec funnel into: given a student and a course they just passed,
 * grant every accreditation the current active-equivalency graph now
 * qualifies them for.
 */
final class GrantAccreditationsForPassedCourseUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $equivalencies,
        private readonly AccreditationRepositoryInterface $accreditations,
        private readonly AccreditationResolver $resolver,
    ) {}

    public function handle(int $studentId, int $passedCourseId): void
    {
        $grants = $this->resolver->resolve(
            $passedCourseId,
            $this->equivalencies->activeGraph(),
            $this->accreditations->recordsFor($studentId),
        );

        foreach ($grants as $grant) {
            $this->accreditations->grant($studentId, $grant->courseId, $grant->equivalencyId);
        }
    }
}
