<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Presentation\Livewire\Forms;

use App\Concerns\TextPatternValidationRules;
use App\Enums\LaboratoryType;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Src\Curriculum\Course\Application\DTOs\CourseDTO;
use Src\Curriculum\Course\Domain\Entities\Course;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;

/**
 * Livewire Form Object — the input boundary for the Course create/edit
 * modal. Gives immediate UX feedback; the domain entity's own
 * assertServiceCourseConsistency() is the authoritative backstop if this
 * validation is ever bypassed.
 */
class CourseForm extends Form
{
    use TextPatternValidationRules;

    public string $code = '';

    public string $name = '';

    public ?int $programId = null;

    public bool $isService = false;

    public bool $isBottleneck = false;

    public bool $requiresLaboratory = false;

    public ?string $laboratoryType = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CourseComponent $component */
        $component = $this->component;

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                $this->institutionalCodePatternRule(),
                Rule::unique('courses', 'code')->ignore($component->editingId),
            ],
            'name' => ['required', 'string', 'max:150', $this->properNamePatternRule()],
            'programId' => [
                'nullable',
                'exists:programs,id',
                Rule::requiredIf(fn () => ! $this->isService),
            ],
            'isService' => ['boolean'],
            'isBottleneck' => ['boolean'],
            'requiresLaboratory' => ['boolean'],
            'laboratoryType' => [
                'nullable',
                Rule::in(array_column(LaboratoryType::cases(), 'value')),
                Rule::requiredIf(fn () => $this->requiresLaboratory),
            ],
        ];
    }

    public function fromEntity(Course $course): void
    {
        $this->code = $course->code();
        $this->name = $course->name();
        $this->programId = $course->programId();
        $this->isService = $course->isService();
        $this->isBottleneck = $course->isBottleneck();
        $this->requiresLaboratory = $course->requiresLaboratory();
        $this->laboratoryType = $course->laboratoryType()?->value;
    }

    public function toDto(): CourseDTO
    {
        return new CourseDTO(
            code: $this->code,
            name: $this->name,
            programId: $this->programId,
            isService: $this->isService,
            isBottleneck: $this->isBottleneck,
            requiresLaboratory: $this->requiresLaboratory,
            laboratoryType: $this->laboratoryType !== null ? LaboratoryType::from($this->laboratoryType) : null,
        );
    }
}
