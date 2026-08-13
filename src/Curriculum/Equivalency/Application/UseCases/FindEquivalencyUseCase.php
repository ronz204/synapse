<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Application\UseCases;

use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyNotFoundException;

final class FindEquivalencyUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Equivalency
    {
        return $this->repository->find($id) ?? throw EquivalencyNotFoundException::withId($id);
    }
}
