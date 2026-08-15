<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\DTOs;

/**
 * Immutable data boundary between Presentation and Application layers.
 * Carries primitives only — no Domain Entities and no Eloquent Models leak
 * across this line in either direction.
 */
final readonly class ModalityDTO
{
    public function __construct(
        public string $name,
        public bool $requiresResolution,
    ) {}
}
