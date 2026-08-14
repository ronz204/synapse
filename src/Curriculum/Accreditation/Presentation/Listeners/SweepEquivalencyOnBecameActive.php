<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Presentation\Listeners;

use Src\Curriculum\Accreditation\Application\UseCases\SweepNewlyActiveEquivalencyForQualifyingStudentsUseCase;
use Src\Curriculum\Equivalency\Domain\Events\EquivalencyBecameActive;

final class SweepEquivalencyOnBecameActive
{
    public function __construct(
        private readonly SweepNewlyActiveEquivalencyForQualifyingStudentsUseCase $useCase,
    ) {}

    public function handle(EquivalencyBecameActive $event): void
    {
        $this->useCase->handle($event->equivalencyId);
    }
}
