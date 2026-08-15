<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Application\UseCases;

use Src\Curriculum\Course\Application\DTOs\CourseDTO;
use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Entities\Course;
use Src\Curriculum\Course\Domain\Exceptions\CourseNotFoundException;

final class UpdateCourseUseCase
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    public function handle(int $id, CourseDTO $dto): Course
    {
        $course = $this->repository->find($id) ?? throw CourseNotFoundException::withId($id);

        Course::assertCodeIsAvailable($this->repository->codeExists($dto->code, excludeId: $id), $dto->code);

        $course->rename($dto->name);
        $course->updateAttributes(
            isService: $dto->isService,
            programId: $dto->programId,
            isBottleneck: $dto->isBottleneck,
            requiresLaboratory: $dto->requiresLaboratory,
            laboratoryType: $dto->laboratoryType,
        );
        // Modality is deliberately untouched here (RC-03): reassigning a
        // course's modality is exclusively AssignModalityToCourseUseCase's
        // job, which runs the write-time resolution gate. This use case
        // must never mutate modalityId, or that gate becomes trivially
        // bypassable via a plain course edit.

        return $this->repository->save($course);
    }
}
