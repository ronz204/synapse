<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Application\UseCases;

use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Entities\Course;
use Src\Curriculum\Course\Domain\Exceptions\CourseNotFoundException;

final class FindCourseUseCase
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Course
    {
        return $this->repository->find($id) ?? throw CourseNotFoundException::withId($id);
    }
}
