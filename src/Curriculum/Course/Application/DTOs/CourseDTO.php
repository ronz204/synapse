<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Application\DTOs;

use App\Enums\LaboratoryType;

/**
 * Immutable data boundary between Presentation and Application layers.
 * Carries primitives only — no Domain Entities and no Eloquent Models leak
 * across this line in either direction.
 */
final readonly class CourseDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?int $programId,
        public bool $isService = false,
        public bool $isBottleneck = false,
        public bool $requiresLaboratory = false,
        public ?LaboratoryType $laboratoryType = null,
    ) {}

    /**
     * @param  array{code: string, name: string, program_id: ?int, is_service?: bool, is_bottleneck?: bool, requires_laboratory?: bool, laboratory_type?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            programId: $data['program_id'],
            isService: $data['is_service'] ?? false,
            isBottleneck: $data['is_bottleneck'] ?? false,
            requiresLaboratory: $data['requires_laboratory'] ?? false,
            laboratoryType: isset($data['laboratory_type'])
                ? LaboratoryType::from($data['laboratory_type'])
                : null,
        );
    }
}
