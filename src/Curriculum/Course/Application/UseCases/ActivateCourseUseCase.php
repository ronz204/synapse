<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Application\UseCases;

use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Exceptions\CourseNotFoundException;

/**
 * Inverse of DeactivateCourseUseCase: flips a deactivated course's `active`
 * flag back on so it can be linked into new plans/levels again. The row
 * itself was never deleted, so this is a plain status flip, not a restore.
 */
final class ActivateCourseUseCase
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    public function handle(int $id): void
    {
        $course = $this->repository->find($id) ?? throw CourseNotFoundException::withId($id);

        $course->activate();

        $this->repository->save($course);
    }
}
