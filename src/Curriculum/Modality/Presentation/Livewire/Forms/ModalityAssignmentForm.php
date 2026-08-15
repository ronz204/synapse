<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Presentation\Livewire\Forms;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use Livewire\WithFileUploads;
use Src\Curriculum\Modality\Application\DTOs\ModalityResolutionDTO;
use Src\Shared\Document\Contracts\AttachableDocument;

/**
 * Livewire Form Object — the input boundary for the combined "assign a
 * modality (with its resolution) to a course" modal. The resolution block
 * is optional as a group: a user can leave it empty (relying on a
 * currently-valid resolution already on file) or fill it entirely (filing
 * a fresh one as part of this same submission) — never a partial mix,
 * enforced by isFilingResolution() driving every resolution field's
 * requiredIf(). `document` stays a TemporaryUploadedFile until the last
 * possible moment, same pattern EquivalencyForm already established.
 */
class ModalityAssignmentForm extends Form
{
    use WithFileUploads;

    public ?int $courseId = null;

    public ?int $modalityId = null;

    public string $resolutionNumber = '';

    public string $approvingBody = '';

    public string $validFrom = '';

    public string $validTo = '';

    public ?TemporaryUploadedFile $document = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $filing = $this->isFilingResolution();

        return [
            'courseId' => ['required', 'integer', 'exists:courses,id'],
            'modalityId' => ['required', 'integer', 'exists:modalities,id'],
            'resolutionNumber' => ['nullable', 'string', 'max:60', Rule::requiredIf($filing)],
            'approvingBody' => ['nullable', 'string', 'max:120', Rule::requiredIf($filing)],
            'validFrom' => ['nullable', 'date', Rule::requiredIf($filing)],
            'validTo' => ['nullable', 'date', 'after_or_equal:validFrom'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240', Rule::requiredIf($filing)],
        ];
    }

    /**
     * True the moment the user has touched any field of the resolution
     * block — from then on, the whole group is required together.
     */
    public function isFilingResolution(): bool
    {
        return $this->resolutionNumber !== ''
            || $this->approvingBody !== ''
            || $this->validFrom !== ''
            || $this->document !== null;
    }

    /**
     * Null when the user isn't filing a new resolution — the use case then
     * relies solely on whatever is already on file for this course/modality
     * pair. Called only once the document is actually being persisted; the
     * component reads $this->document itself and hands the resulting value
     * object back here, same division of responsibility as EquivalencyForm.
     */
    public function toResolutionDto(?AttachableDocument $document): ?ModalityResolutionDTO
    {
        if (! $this->isFilingResolution()) {
            return null;
        }

        return new ModalityResolutionDTO(
            number: $this->resolutionNumber,
            approvingBody: $this->approvingBody,
            validFrom: CarbonImmutable::parse($this->validFrom),
            validTo: $this->validTo !== '' ? CarbonImmutable::parse($this->validTo) : null,
            document: $document,
        );
    }
}
