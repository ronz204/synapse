<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Application\DTOs;

use App\Enums\EquivalencyDirection;
use Src\Shared\Document\Contracts\AttachableDocument;

/**
 * Immutable data boundary between Presentation and Application layers.
 * Carries primitives (plus the shared AttachableDocument value object) only
 * — no Domain Entities and no Eloquent Models leak across this line in
 * either direction.
 *
 * `document` is nullable on purpose: RegisterEquivalencyUseCase needs
 * something concrete to assert against to throw
 * EquivalencyDocumentRequiredException as its own backstop, even though
 * EquivalencyForm already requires it client-side.
 */
final readonly class EquivalencyDTO
{
    public function __construct(
        public int $sourceCourseId,
        public int $targetCourseId,
        public EquivalencyDirection $direction,
        public string $resolutionNumber,
        public ?AttachableDocument $document,
    ) {}
}
