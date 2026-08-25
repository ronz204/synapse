<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Application\UseCases;

use Src\Curriculum\Equivalency\Domain\Contracts\EquivalencyRepositoryInterface;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyDocumentNotFoundException;
use Src\Shared\Document\Contracts\StoredDocument;

final class GetEquivalencyDocumentUseCase
{
    public function __construct(
        private readonly EquivalencyRepositoryInterface $repository,
    ) {}

    /**
     * The caller is expected to have already resolved the equivalency
     * itself (e.g. via FindEquivalencyUseCase, for authorization) before
     * calling this — so a missing document at this point always means "no
     * document attached", never "equivalency not found".
     */
    public function handle(int $equivalencyId): StoredDocument
    {
        return $this->repository->findDocument($equivalencyId) ?? throw EquivalencyDocumentNotFoundException::forEquivalency($equivalencyId);
    }
}
