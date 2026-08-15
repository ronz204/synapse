<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\UseCases;

use Src\Curriculum\Modality\Application\DTOs\ModalityDTO;
use Src\Curriculum\Modality\Domain\Contracts\ModalityRepositoryInterface;
use Src\Curriculum\Modality\Domain\Entities\Modality;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityNameAlreadyExistsException;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityNotFoundException;

final class UpdateModalityUseCase
{
    public function __construct(
        private readonly ModalityRepositoryInterface $repository,
    ) {}

    public function handle(int $id, ModalityDTO $dto): Modality
    {
        $modality = $this->repository->find($id) ?? throw ModalityNotFoundException::forId($id);

        if ($this->repository->nameExists($dto->name, excludeId: $id)) {
            throw ModalityNameAlreadyExistsException::forName($dto->name);
        }

        $modality->rename($dto->name);
        $modality->setRequiresResolution($dto->requiresResolution);

        return $this->repository->save($modality);
    }
}
