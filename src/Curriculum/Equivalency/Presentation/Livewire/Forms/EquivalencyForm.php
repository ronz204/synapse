<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Presentation\Livewire\Forms;

use App\Concerns\TextPatternValidationRules;
use App\Enums\EquivalencyDirection;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use Livewire\WithFileUploads;
use Src\Curriculum\Equivalency\Application\DTOs\EquivalencyDTO;
use Src\Shared\Document\Contracts\AttachableDocument;

/**
 * Livewire Form Object — the input boundary for the equivalency
 * registration modal. Gives immediate UX feedback; the domain's own
 * EquivalencyDocumentRequiredException/EquivalencySourceAndTargetMustDifferException
 * are the authoritative backstops if this validation is ever bypassed.
 *
 * `document` deliberately stays a TemporaryUploadedFile until the very last
 * moment it is actually persisted — see EquivalencyComponent::register()/
 * resolveContradiction() — so a cycle or contradiction rejection never
 * leaves a file written to permanent storage.
 */
class EquivalencyForm extends Form
{
    use TextPatternValidationRules, WithFileUploads;

    public ?int $sourceCourseId = null;

    public ?int $targetCourseId = null;

    public string $direction = '';

    public string $resolutionNumber = '';

    public ?TemporaryUploadedFile $document = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sourceCourseId' => ['required', 'integer', 'exists:courses,id', 'different:targetCourseId'],
            'targetCourseId' => ['required', 'integer', 'exists:courses,id'],
            'direction' => ['required', Rule::in(array_column(EquivalencyDirection::cases(), 'value'))],
            'resolutionNumber' => ['required', 'string', 'max:60', $this->institutionalCodePatternRule()],
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * Kept word-for-word identical to
     * EquivalencyDocumentRequiredException::missing()'s message — this Form
     * rejects a missing document before the domain layer ever runs, so
     * without this override the user would see Laravel's generic "The
     * document field is required." instead of the wording the acceptance
     * criteria mandate.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.required' => __('You must attach the resolution that approves this equivalency.'),
        ];
    }

    /**
     * Called only once the document is actually being persisted — the
     * component reads $this->document itself (hash, size, mime, and the
     * store() call) and hands the resulting value object back here, so this
     * Form never performs disk I/O on its own.
     */
    public function toDto(?AttachableDocument $document): EquivalencyDTO
    {
        return new EquivalencyDTO(
            sourceCourseId: (int) $this->sourceCourseId,
            targetCourseId: (int) $this->targetCourseId,
            direction: EquivalencyDirection::from($this->direction),
            resolutionNumber: $this->resolutionNumber,
            document: $document,
        );
    }
}
