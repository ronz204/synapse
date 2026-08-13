<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Application\UseCases;

use Src\Curriculum\Equivalency\Application\DTOs\EquivalencyDTO;
use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyDocumentRequiredException;

/**
 * Persists both sides of a resolved contradiction: the candidate row
 * (Active if it prevails, Superseded pointing at the existing record
 * otherwise) and, if the candidate prevails, the existing record
 * transitioned to Superseded pointing at the candidate. Neither the
 * candidate nor the existing record is ever discarded — see
 * EloquentEquivalencyRepository::resolveContradiction().
 */
final class ResolveEquivalencyContradictionUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $equivalencies,
    ) {}

    public function handle(EquivalencyDTO $candidateDto, int $existingEquivalencyId, bool $candidatePrevails): Equivalency
    {
        if ($candidateDto->document === null) {
            throw EquivalencyDocumentRequiredException::missing();
        }

        $candidate = Equivalency::create(
            $candidateDto->sourceCourseId,
            $candidateDto->targetCourseId,
            $candidateDto->direction,
            $candidateDto->resolutionNumber,
        );

        return $this->equivalencies->resolveContradiction($candidate, $candidateDto->document, $existingEquivalencyId, $candidatePrevails);
    }
}
