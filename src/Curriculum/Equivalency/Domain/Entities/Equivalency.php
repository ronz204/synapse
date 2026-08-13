<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Entities;

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencySourceAndTargetMustDifferException;

/**
 * Equivalency — Aggregate Root of the Curriculum bounded context's graph
 * integrity slice.
 *
 * Pure PHP. Zero framework coupling — no Eloquent, no Illuminate imports.
 * References Course only by id, the same way StudyPlan does — Course stays
 * its own aggregate. Append-only by design: an Equivalency is created once
 * and only ever transitions to Superseded via the contradiction-resolution
 * flow, it is never edited or deleted.
 */
final class Equivalency
{
    private function __construct(
        private readonly ?int $id,
        private readonly int $sourceCourseId,
        private readonly int $targetCourseId,
        private readonly EquivalencyDirection $direction,
        private readonly string $resolutionNumber,
        private EquivalencyStatus $status,
        private ?int $supersededById,
    ) {
        $this->assertCoursesDiffer();
    }

    public static function create(int $sourceCourseId, int $targetCourseId, EquivalencyDirection $direction, string $resolutionNumber): self
    {
        return new self(
            id: null,
            sourceCourseId: $sourceCourseId,
            targetCourseId: $targetCourseId,
            direction: $direction,
            resolutionNumber: $resolutionNumber,
            status: EquivalencyStatus::Active,
            supersededById: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $sourceCourseId,
        int $targetCourseId,
        EquivalencyDirection $direction,
        string $resolutionNumber,
        EquivalencyStatus $status,
        ?int $supersededById,
    ): self {
        return new self(
            id: $id,
            sourceCourseId: $sourceCourseId,
            targetCourseId: $targetCourseId,
            direction: $direction,
            resolutionNumber: $resolutionNumber,
            status: $status,
            supersededById: $supersededById,
        );
    }

    /**
     * Never deletes: a status transition backed by the database's
     * active_key uniqueness index. The loser always keeps pointing at
     * whichever equivalency prevailed over it.
     */
    public function supersedeBy(int $winnerId): void
    {
        $this->status = EquivalencyStatus::Superseded;
        $this->supersededById = $winnerId;
    }

    private function assertCoursesDiffer(): void
    {
        if ($this->sourceCourseId === $this->targetCourseId) {
            throw EquivalencySourceAndTargetMustDifferException::forCourse($this->sourceCourseId);
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sourceCourseId(): int
    {
        return $this->sourceCourseId;
    }

    public function targetCourseId(): int
    {
        return $this->targetCourseId;
    }

    public function direction(): EquivalencyDirection
    {
        return $this->direction;
    }

    public function resolutionNumber(): string
    {
        return $this->resolutionNumber;
    }

    public function status(): EquivalencyStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === EquivalencyStatus::Active;
    }

    public function supersededById(): ?int
    {
        return $this->supersededById;
    }
}
