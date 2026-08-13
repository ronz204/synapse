<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Application\UseCases;

use Src\Curriculum\Course\Application\DTOs\CourseDTO;
use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Entities\Course;

final class CreateCourseUseCase
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    public function handle(CourseDTO $dto): Course
    {
        Course::assertCodeIsAvailable($this->repository->codeExists($dto->code), $dto->code);

        $course = Course::create(
            code: $dto->code,
            name: $dto->name,
            programId: $dto->programId,
            modalityId: $dto->modalityId,
            isService: $dto->isService,
            isBottleneck: $dto->isBottleneck,
            requiresLaboratory: $dto->requiresLaboratory,
            laboratoryType: $dto->laboratoryType,
        );

        return $this->repository->save($course);
    }
}
