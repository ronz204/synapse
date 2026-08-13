<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Entities;

/**
 * Level — child entity of the StudyPlan aggregate. Levels are ordered per
 * plan (unique `number` per plan, no separate name field) and hold the
 * courses linked to them, each with the credits value specific to that link.
 */
final class Level
{
    /**
     * @param  array<int, CourseLink>  $courseLinks
     */
    private function __construct(
        private readonly ?int $id,
        private readonly int $number,
        private array $courseLinks,
    ) {}

    /**
     * @param  array<int, CourseLink>  $courseLinks
     */
    public static function create(int $number, array $courseLinks = []): self
    {
        return new self(id: null, number: $number, courseLinks: $courseLinks);
    }

    /**
     * @param  array<int, CourseLink>  $courseLinks
     */
    public static function reconstitute(int $id, int $number, array $courseLinks = []): self
    {
        return new self(id: $id, number: $number, courseLinks: $courseLinks);
    }

    public function linksCourse(int $courseId): bool
    {
        foreach ($this->courseLinks as $link) {
            if ($link->courseId === $courseId) {
                return true;
            }
        }

        return false;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function number(): int
    {
        return $this->number;
    }

    /**
     * @return array<int, CourseLink>
     */
    public function courseLinks(): array
    {
        return $this->courseLinks;
    }
}
