<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\UseCases;

use Src\Curriculum\Modality\Domain\Contracts\ModalityRepositoryInterface;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityInUseException;

final class DeleteModalityUseCase
{
    public function __construct(
        private readonly ModalityRepositoryInterface $repository,
    ) {}

    public function handle(int $id): void
    {
        if ($this->repository->isInUse($id)) {
            throw ModalityInUseException::forId($id);
        }

        $this->repository->delete($id);
    }
}
