<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\UseCases;

use Src\Curriculum\Modality\Domain\Contracts\ModalityResolutionRepositoryInterface;
use Src\Shared\Document\Contracts\StoredDocument;

final class GetModalityResolutionDocumentUseCase
{
    public function __construct(
        private readonly ModalityResolutionRepositoryInterface $repository,
    ) {}

    public function handle(int $modalityResolutionId): ?StoredDocument
    {
        return $this->repository->findDocument($modalityResolutionId);
    }
}
