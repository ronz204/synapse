<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Application\UseCases;

use Src\Curriculum\Equivalency\Application\DTOs\EquivalencyDTO;
use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\Exceptions\CycleDetectedException;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyContradictionException;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyDocumentRequiredException;
use Src\Curriculum\Equivalency\Domain\Services\ContradictionDetectionService;
use Src\Curriculum\Equivalency\Domain\Services\CycleDetectionService;

/**
 * Exact order required by the spec: document presence -> cycle check ->
 * contradiction check -> persistence. The document is never moved to
 * permanent storage before this point — register() (the adapter) is the
 * only place that happens, so a cycle or contradiction rejection never
 * leaves an orphaned file on disk.
 */
final class RegisterEquivalencyUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $equivalencies,
        private readonly CycleDetectionService $cycleDetection,
        private readonly ContradictionDetectionService $contradictionDetection,
    ) {}

    public function handle(EquivalencyDTO $dto): Equivalency
    {
        if ($dto->document === null) {
            throw EquivalencyDocumentRequiredException::missing();
        }

        $source = $this->equivalencies->courseNode($dto->sourceCourseId);
        $target = $this->equivalencies->courseNode($dto->targetCourseId);

        $cycleResult = $this->cycleDetection->detect(
            $this->equivalencies->activeGraph(),
            $dto->direction,
            $source,
            $target,
        );

        if ($cycleResult->hasCycle) {
            throw CycleDetectedException::forChain($cycleResult);
        }

        $existing = $this->equivalencies->findActiveForPair($dto->sourceCourseId, $dto->targetCourseId, $dto->direction);

        if ($this->contradictionDetection->detect($existing)->hasContradiction && $existing !== null) {
            // $existing is always a persisted, DB-loaded record here (never a
            // freshly created one), so its id is never actually null — id()'s
            // ?int return type only accommodates the not-yet-persisted
            // construction path this branch never takes.
            throw EquivalencyContradictionException::forConflict($existing->id() ?? 0);
        }

        $equivalency = Equivalency::create($dto->sourceCourseId, $dto->targetCourseId, $dto->direction, $dto->resolutionNumber);

        return $this->equivalencies->register($equivalency, $dto->document);
    }
}
