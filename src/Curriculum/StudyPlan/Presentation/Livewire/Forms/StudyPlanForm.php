<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Presentation\Livewire\Forms;

use App\Enums\PlanClassification;
use DateTimeImmutable;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Src\Curriculum\StudyPlan\Application\DTOs\StudyPlanDTO;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;
use Src\Curriculum\StudyPlan\Presentation\Livewire\StudyPlanComponent;

/**
 * Livewire Form Object — the input boundary for the plan's basic fields
 * (create/edit modal). Structure editing (levels/course links/prerequisites)
 * is not a Form Object: it's incremental array state directly on
 * StudyPlanComponent, since it's UI interaction state (add/remove rows)
 * rather than a single validated submission.
 */
class StudyPlanForm extends Form
{
    public ?int $programId = null;

    public string $name = '';

    public string $implementationYear = '';

    public string $classification = '';

    public ?string $enrollmentClosingDate = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var StudyPlanComponent $component */
        $component = $this->component;

        return [
            'programId' => ['required', 'exists:programs,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('study_plans', 'name')
                    ->where(fn ($query) => $query->where('program_id', $this->programId))
                    ->ignore($component->editingId),
            ],
            'implementationYear' => ['required', 'integer', 'min:1900', 'max:2100'],
            'classification' => ['required', Rule::in(array_column(PlanClassification::cases(), 'value'))],
            'enrollmentClosingDate' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $this->classification === PlanClassification::Terminal->value),
            ],
        ];
    }

    public function fromEntity(StudyPlan $studyPlan): void
    {
        $this->programId = $studyPlan->programId();
        $this->name = $studyPlan->name();
        $this->implementationYear = (string) $studyPlan->implementationYear();
        $this->classification = $studyPlan->classification()->value;
        $this->enrollmentClosingDate = $studyPlan->enrollmentClosingDate()?->format('Y-m-d');
    }

    public function toDto(): StudyPlanDTO
    {
        return new StudyPlanDTO(
            programId: (int) $this->programId,
            name: $this->name,
            implementationYear: (int) $this->implementationYear,
            classification: PlanClassification::from($this->classification),
            enrollmentClosingDate: filled($this->enrollmentClosingDate)
                ? new DateTimeImmutable($this->enrollmentClosingDate)
                : null,
        );
    }
}
