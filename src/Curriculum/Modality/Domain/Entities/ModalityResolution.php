<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Entities;

use Carbon\CarbonImmutable;

/**
 * ModalityResolution — Aggregate Root of the assignment half of the
 * Modality bounded context. References Course only by id, same boundary
 * EquivalencyEdge/StudyPlan already keep against the Course aggregate.
 * Immutable once created: a resolution is never edited or deleted, only
 * ever superseded by filing a new one (see elocuent.md's "Model Historical
 * State as a Status, Not a Deletion").
 */
final class ModalityResolution
{
    private function __construct(
        private readonly ?int $id,
        private readonly int $courseId,
        private readonly int $modalityId,
        private readonly string $number,
        private readonly string $approvingBody,
        private readonly CarbonImmutable $validFrom,
        private readonly ?CarbonImmutable $validTo,
    ) {}

    public static function create(
        int $courseId,
        int $modalityId,
        string $number,
        string $approvingBody,
        CarbonImmutable $validFrom,
        ?CarbonImmutable $validTo,
    ): self {
        return new self(
            id: null,
            courseId: $courseId,
            modalityId: $modalityId,
            number: $number,
            approvingBody: $approvingBody,
            validFrom: $validFrom,
            validTo: $validTo,
        );
    }

    public static function reconstitute(
        int $id,
        int $courseId,
        int $modalityId,
        string $number,
        string $approvingBody,
        CarbonImmutable $validFrom,
        ?CarbonImmutable $validTo,
    ): self {
        return new self(
            id: $id,
            courseId: $courseId,
            modalityId: $modalityId,
            number: $number,
            approvingBody: $approvingBody,
            validFrom: $validFrom,
            validTo: $validTo,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function courseId(): int
    {
        return $this->courseId;
    }

    public function modalityId(): int
    {
        return $this->modalityId;
    }

    public function number(): string
    {
        return $this->number;
    }

    public function approvingBody(): string
    {
        return $this->approvingBody;
    }

    public function validFrom(): CarbonImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?CarbonImmutable
    {
        return $this->validTo;
    }
}
