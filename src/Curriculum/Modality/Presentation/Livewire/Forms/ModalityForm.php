<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Presentation\Livewire\Forms;

use App\Concerns\TextPatternValidationRules;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Src\Curriculum\Modality\Application\DTOs\ModalityDTO;
use Src\Curriculum\Modality\Domain\Entities\Modality;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityComponent;

/**
 * Livewire Form Object — the input boundary for the Modality create/edit
 * modal. Gives immediate UX feedback; the use cases' own
 * ModalityNameAlreadyExistsException is the authoritative backstop if this
 * validation is ever bypassed.
 */
class ModalityForm extends Form
{
    use TextPatternValidationRules;

    public string $name = '';

    public bool $requiresResolution = false;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ModalityComponent $component */
        $component = $this->component;

        return [
            'name' => [
                'required',
                'string',
                'max:40',
                $this->properNamePatternRule(),
                Rule::unique('modalities', 'name')->ignore($component->editingId),
            ],
            'requiresResolution' => ['boolean'],
        ];
    }

    public function fromEntity(Modality $modality): void
    {
        $this->name = $modality->name();
        $this->requiresResolution = $modality->requiresResolution();
    }

    public function toDto(): ModalityDTO
    {
        return new ModalityDTO(name: $this->name, requiresResolution: $this->requiresResolution);
    }
}
