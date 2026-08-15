<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Entities;

/**
 * Modality — Aggregate Root of the catalog half of the Modality bounded
 * context. Pure PHP, zero framework coupling. `requiresResolution` is what
 * ModalityAssignmentEligibility gates on; it carries no validity-window
 * data of its own — that lives entirely on ModalityResolution.
 */
final class Modality
{
    private function __construct(
        private readonly ?int $id,
        private string $name,
        private bool $requiresResolution,
    ) {}

    public static function create(string $name, bool $requiresResolution): self
    {
        return new self(id: null, name: $name, requiresResolution: $requiresResolution);
    }

    public static function reconstitute(int $id, string $name, bool $requiresResolution): self
    {
        return new self(id: $id, name: $name, requiresResolution: $requiresResolution);
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function setRequiresResolution(bool $requiresResolution): void
    {
        $this->requiresResolution = $requiresResolution;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function requiresResolution(): bool
    {
        return $this->requiresResolution;
    }
}
