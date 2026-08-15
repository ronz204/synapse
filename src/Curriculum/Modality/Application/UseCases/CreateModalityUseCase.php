<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\UseCases;

use Src\Curriculum\Modality\Application\DTOs\ModalityDTO;
use Src\Curriculum\Modality\Domain\Contracts\ModalityRepositoryInterface;
use Src\Curriculum\Modality\Domain\Entities\Modality;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityNameAlreadyExistsException;

final class CreateModalityUseCase
{
    public function __construct(
        private readonly ModalityRepositoryInterface $repository,
    ) {}

    public function handle(ModalityDTO $dto): Modality
    {
        if ($this->repository->nameExists($dto->name)) {
            throw ModalityNameAlreadyExistsException::forName($dto->name);
        }

        $modality = Modality::create($dto->name, $dto->requiresResolution);

        return $this->repository->save($modality);
    }
}
