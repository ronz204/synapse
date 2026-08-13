<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Application\UseCases;

use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Exceptions\CourseNotFoundException;

/**
 * Never a row delete: a course can be linked to plans that must keep
 * referencing it historically, so "removing" a course is always a status
 * flip via the `active` flag.
 */
final class DeactivateCourseUseCase
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    public function handle(int $id): void
    {
        $course = $this->repository->find($id) ?? throw CourseNotFoundException::withId($id);

        $course->deactivate();

        $this->repository->save($course);
    }
}
