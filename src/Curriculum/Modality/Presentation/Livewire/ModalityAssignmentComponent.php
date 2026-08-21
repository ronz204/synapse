<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use App\Models\Course as CourseModel;
use App\Models\Modality as ModalityModel;
use App\Models\ModalityResolution as ModalityResolutionModel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Src\Curriculum\Modality\Application\UseCases\AssignModalityToCourseUseCase;
use Src\Curriculum\Modality\Application\UseCases\GetModalityResolutionDocumentUseCase;
use Src\Curriculum\Modality\Domain\Entities\ModalityResolution;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityResolutionDocumentRequiredException;
use Src\Curriculum\Modality\Domain\Exceptions\NoValidModalityResolutionException;
use Src\Curriculum\Modality\Presentation\Livewire\Forms\ModalityAssignmentForm;
use Src\Shared\Document\Contracts\AttachableDocument;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The single combined "assign a modality (with its resolution) to a
 * course" flow (RC-03 spec, decision #4 of rc3.plan.md) — resolution
 * fields are optional as a group; the use case decides whether the gate is
 * satisfied by what's already on file, what's freshly submitted, or both.
 */
#[Layout('layouts.dashboard', ['title' => 'Modality Assignments', 'subtitle' => 'Assign a teaching modality to a course, backed by a resolution when required'])]
class ModalityAssignmentComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;
    use WithFileUploads;

    protected string $tableMode = 'client';

    public bool $showModal = false;

    public ModalityAssignmentForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', ModalityResolution::class);
        $this->sortKey = 'code';
    }

    public function openAssignModal(): void
    {
        $this->authorize('create', ModalityResolution::class);

        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function assign(AssignModalityToCourseUseCase $useCase): void
    {
        $this->form->validate();
        $this->authorize('create', ModalityResolution::class);

        $document = $this->buildDocumentFromUpload();

        try {
            $useCase->handle(
                (int) $this->form->courseId,
                (int) $this->form->modalityId,
                $this->form->toResolutionDto($document),
            );
        } catch (NoValidModalityResolutionException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return;
        } catch (ModalityResolutionDocumentRequiredException $e) {
            $this->addError('form.document', __($e->getMessage()));

            return;
        }

        $this->showModal = false;
        Flux::toast(variant: 'success', text: __('Modality assigned.'));
    }

    /**
     * Reads the still-temporary upload's metadata (safe, read-only) without
     * moving it anywhere permanent — see AttachableDocument's docblock for
     * why the actual move happens later, inside the use case's own
     * persistence step, only once the eligibility gate has passed.
     */
    private function buildDocumentFromUpload(): ?AttachableDocument
    {
        $file = $this->form->document;

        if ($file === null) {
            return null;
        }

        $uploaderId = auth()->id();

        return new AttachableDocument(
            originalName: $file->getClientOriginalName(),
            temporaryPath: $file->getRealPath(),
            disk: 'local',
            directory: 'resoluciones',
            mimeType: $file->getMimeType(),
            sizeBytes: $file->getSize() ?: 0,
            hashSha256: hash_file('sha256', $file->getRealPath()) ?: '',
            uploaderId: $uploaderId === null ? null : (int) $uploaderId,
        );
    }

    /**
     * Gated on the same permission as viewing the assignment listing itself
     * — anyone who can see a course's backing resolution number can also
     * inspect the document behind it, same reasoning
     * EquivalencyComponent::downloadDocument() already documents.
     */
    public function downloadDocument(int $modalityResolutionId, GetModalityResolutionDocumentUseCase $useCase): StreamedResponse
    {
        $this->authorize('viewAny', ModalityResolution::class);

        $document = $useCase->handle($modalityResolutionId);

        abort_if($document === null, 404);

        return Storage::disk($document->disk)->download($document->path, $document->originalName);
    }

    /**
     * $search arrives from the view, not from $this->search: in client mode
     * the search box is bound to Alpine (not wire:model), so the live
     * filter only exists in the browser — same reasoning CourseComponent's
     * exportPdf() already documents.
     */
    public function exportPdf(PdfExporterInterface $exporter, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', ModalityResolution::class);

        return $this->streamPdf(
            __('Modality Assignments'),
            $this->exportHeaders(),
            $this->exportableRows($search),
            Str::slug(__('Modality Assignments')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', ModalityResolution::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($search),
            Str::slug(__('Modality Assignments')).'.xlsx',
            $exporter,
        );
    }

    public function render(): View
    {
        return view('curriculum.modality.livewire.modality-assignment-component', [
            'rows' => $this->assignmentRows(),
            'courseOptions' => $this->courseOptions(),
            'modalityOptions' => $this->modalityOptions(),
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'modalityName', 'label' => __('Modality')],
            ['key' => 'resolutionNumber', 'label' => __('Resolution number')],
            ['key' => 'validFrom', 'label' => __('Valid from')],
            ['key' => 'validTo', 'label' => __('Valid to')],
            ['key' => 'isCurrentlyValid', 'label' => __('Currently valid'), 'format' => fn (bool $v): string => $v ? __('Yes') : __('No')],
        ];
    }

    /**
     * Read-only "course → current modality" projection, including the
     * backing resolution's validity window when the modality requires one
     * — so an expired resolution reads as expired instead of unconditionally
     * current (the risk this slice's spec explicitly calls out). Pragmatic
     * cross-aggregate read directly against Eloquent models, same coupling
     * EquivalencyComponent::toRow() already uses for course codes — no
     * dedicated use case for a purely presentational listing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function assignmentRows(): array
    {
        return CourseModel::query()->active()->with('modality')->orderBy('code')->get()
            ->map(function (CourseModel $course): array {
                $modality = $course->modality;
                $requiresResolution = $modality !== null && $modality->requires_resolution;
                $resolution = null;

                if ($requiresResolution) {
                    $resolution = ModalityResolutionModel::query()
                        ->where('course_id', $course->id)
                        ->where('modality_id', $course->modality_id)
                        ->valid()
                        ->latest('valid_from')
                        ->first()
                        ?? ModalityResolutionModel::query()
                            ->where('course_id', $course->id)
                            ->where('modality_id', $course->modality_id)
                            ->latest('valid_from')
                            ->first();
                }

                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                    'modalityName' => $modality?->name,
                    'requiresResolution' => $requiresResolution,
                    'resolutionId' => $resolution?->id,
                    'resolutionNumber' => $resolution?->resolution_number,
                    'validFrom' => $resolution?->valid_from?->toDateString(),
                    'validTo' => $resolution?->valid_to?->toDateString(),
                    'isCurrentlyValid' => $resolution !== null
                        && $resolution->valid_from->lessThanOrEqualTo(now())
                        && ($resolution->valid_to === null || $resolution->valid_to->greaterThanOrEqualTo(now())),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(?string $search): array
    {
        $candidate = filled($search) ? $search : $this->search;

        if ($candidate === '') {
            return $this->assignmentRows();
        }

        return array_values(array_filter(
            $this->assignmentRows(),
            fn (array $row) => str_contains(mb_strtolower($row['code']), mb_strtolower($candidate))
                || str_contains(mb_strtolower($row['name']), mb_strtolower($candidate)),
        ));
    }

    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function courseOptions(): array
    {
        return CourseModel::query()->active()->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn (CourseModel $course) => ['id' => $course->id, 'code' => $course->code, 'name' => $course->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, requiresResolution: bool}>
     */
    private function modalityOptions(): array
    {
        return ModalityModel::query()->orderBy('name')->get(['id', 'name', 'requires_resolution'])
            ->map(fn (ModalityModel $modality) => [
                'id' => $modality->id,
                'name' => $modality->name,
                'requiresResolution' => $modality->requires_resolution,
            ])
            ->all();
    }
}
