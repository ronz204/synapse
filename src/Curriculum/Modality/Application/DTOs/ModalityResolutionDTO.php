<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Application\DTOs;

use Carbon\CarbonImmutable;
use Src\Shared\Document\Contracts\AttachableDocument;

/**
 * Immutable data boundary between Presentation and Application layers.
 * `document` is nullable so AssignModalityToCourseUseCase has something
 * concrete to throw ModalityResolutionDocumentRequiredException against,
 * same two-layer pattern EquivalencyDTO already uses for RC-02.
 */
final readonly class ModalityResolutionDTO
{
    public function __construct(
        public string $number,
        public string $approvingBody,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validTo,
        public ?AttachableDocument $document,
    ) {}
}
