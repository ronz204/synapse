<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Application\UseCases;

use Src\Curriculum\AcademicHistory\Domain\Contracts\AcademicHistoryRepositoryInterface;
use Src\Curriculum\AcademicHistory\Domain\Entities\StudentAcademicHistory;
use Src\Curriculum\AcademicHistory\Domain\Exceptions\StudentAcademicHistoryNotFoundException;

/**
 * Reads one student's simplified internal history — RC-02b's output.
 *
 * Throws rather than returning null: a caller reaching this point always
 * came from a row in the listing, so a missing student is a broken
 * assumption, not an ordinary outcome the view should render around.
 */
final class GetStudentAcademicHistoryUseCase
{
    public function __construct(
        private readonly AcademicHistoryRepositoryInterface $repository,
    ) {}

    public function handle(int $studentId): StudentAcademicHistory
    {
        return $this->repository->findHistory($studentId)
            ?? throw StudentAcademicHistoryNotFoundException::forStudent($studentId);
    }
}
