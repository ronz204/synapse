<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Application\UseCases;

use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyNotFoundException;
use Src\Shared\Document\Contracts\StoredDocument;

final class GetEquivalencyDocumentUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $repository,
    ) {}

    public function handle(int $equivalencyId): StoredDocument
    {
        return $this->repository->findDocument($equivalencyId) ?? throw EquivalencyNotFoundException::withId($equivalencyId);
    }
}
