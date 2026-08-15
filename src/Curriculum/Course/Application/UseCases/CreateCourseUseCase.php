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

        // modalityId is deliberately omitted: a newly created course always
        // starts unassigned (EloquentCourseRepository::save() then resolves
        // the default-to-Presencial rule). Assigning any other modality is
        // exclusively AssignModalityToCourseUseCase's job (RC-03), which
        // runs the write-time resolution gate this use case must not bypass.
        $course = Course::create(
            code: $dto->code,
            name: $dto->name,
            programId: $dto->programId,
            isService: $dto->isService,
            isBottleneck: $dto->isBottleneck,
            requiresLaboratory: $dto->requiresLaboratory,
            laboratoryType: $dto->laboratoryType,
        );

        return $this->repository->save($course);
    }
}
