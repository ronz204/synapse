<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\UseCases;

use Src\Curriculum\Modality\Domain\Contracts\ModalityRepositoryInterface;
use Src\Curriculum\Modality\Domain\Entities\Modality;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityNotFoundException;

final class FindModalityUseCase
{
    public function __construct(
        private readonly ModalityRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Modality
    {
        return $this->repository->find($id) ?? throw ModalityNotFoundException::forId($id);
    }
}
