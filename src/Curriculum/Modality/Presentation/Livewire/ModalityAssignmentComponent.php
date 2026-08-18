<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use App\Models\Course as CourseModel;
use App\Models\Modality as ModalityModel;
use App\Models\ModalityResolution as ModalityResolutionModel;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

    /**
     * Server mode: this listing projects every active course, ~800 at the
     * target volume, past the 200-row threshold of research decision D-01.
     */
    protected string $tableMode = 'server';

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
            $this->dispatch('toast', variant: 'danger', text: $e->getMessage());

            return;
        } catch (ModalityResolutionDocumentRequiredException $e) {
            $this->addError('form.document', $e->getMessage());

            return;
        }

        $this->showModal = false;
        $this->dispatch('toast', variant: 'success', text: __('Modality assigned.'));
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
    public function exportPdf(?string $search = null): void
    {
        $this->authorize('exportPdf', ModalityResolution::class);

        $this->queuePdf(
            __('Modality Assignments'),
            $this->exportHeaders(),
            $this->exportableRows($search),
            Str::slug(__('Modality Assignments')).'.pdf',
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
        [$rows, $total] = $this->assignmentPage();

        return view('curriculum.modality.livewire.modality-assignment-component', [
            'rows' => $rows,
            'assignments' => new LengthAwarePaginator(
                items: $rows,
                total: $total,
                perPage: $this->perPage,
                currentPage: $this->page,
            ),
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
     * Read-only "course → current modality" projection, one page at a time.
     *
     * Includes the backing resolution's validity window when the modality
     * requires one, so an expired resolution reads as expired instead of
     * unconditionally current (the risk this slice's spec calls out).
     * Pragmatic cross-aggregate read directly against Eloquent models, the same
     * coupling EquivalencyComponent already uses for course codes — no
     * dedicated use case for a purely presentational listing.
     *
     * Two things changed here for feature 002-perceived-performance and both
     * matter at the target volume (800 courses):
     *
     * 1. It pages. The previous version loaded every active course on every
     *    render and shipped all 800 into the payload, four times the 200-row
     *    threshold of research decision D-01.
     * 2. Resolutions are fetched once for the whole page instead of up to twice
     *    per course. The two-step fallback (a valid resolution if there is one,
     *    otherwise the latest expired one, so an expired resolution reads as
     *    expired rather than as absent) is preserved exactly — it is resolved
     *    in PHP over one result set instead of by two queries per row.
     *
     * @param  bool  $all  Exports need the whole filtered set, not one page.
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function assignmentPage(bool $all = false, ?string $search = null): array
    {
        $term = $search ?? $this->search;

        $query = CourseModel::query()->active()->with('modality');

        if (filled($term)) {
            $query->where(function ($inner) use ($term): void {
                $inner->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            });
        }

        $column = in_array($this->sortKey, ['code', 'name'], true) ? $this->sortKey : 'code';
        $direction = $this->sortDir === 'desc' ? 'desc' : 'asc';

        $total = (clone $query)->count();

        $courses = $all
            ? $query->orderBy($column, $direction)->get()
            : $query->orderBy($column, $direction)
                ->forPage(max(1, $this->page), $this->perPage)
                ->get();

        $resolutions = $this->resolutionsForCourses($courses);

        $rows = $courses->map(function (CourseModel $course) use ($resolutions): array {
            $modality = $course->modality;
            $requiresResolution = $modality !== null && $modality->requires_resolution;
            $resolution = $requiresResolution
                ? ($resolutions[$course->id.'-'.$course->modality_id] ?? null)
                : null;

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
        })->all();

        return [$rows, $total];
    }

    /**
     * Best resolution per (course, modality) for a whole page, in one query.
     *
     * "Best" keeps the original preference: a currently valid resolution wins;
     * failing that, the most recent expired one, so the view can say "Expired"
     * instead of "None on file" — the distinction this slice's spec calls out.
     *
     * @param  Collection<int, CourseModel>  $courses
     * @return array<string, ModalityResolutionModel>
     */
    private function resolutionsForCourses($courses): array
    {
        $courseIds = $courses
            ->filter(fn (CourseModel $course): bool => $course->modality?->requires_resolution === true)
            ->pluck('id')
            ->all();

        if ($courseIds === []) {
            return [];
        }

        $best = [];

        ModalityResolutionModel::query()
            ->whereIn('course_id', $courseIds)
            ->orderBy('valid_from')
            ->get()
            ->each(function (ModalityResolutionModel $resolution) use (&$best): void {
                $key = $resolution->course_id.'-'.$resolution->modality_id;
                $current = $best[$key] ?? null;

                $isValid = $resolution->valid_from->lessThanOrEqualTo(now())
                    && ($resolution->valid_to === null || $resolution->valid_to->greaterThanOrEqualTo(now()));

                $currentIsValid = $current !== null
                    && $current->valid_from->lessThanOrEqualTo(now())
                    && ($current->valid_to === null || $current->valid_to->greaterThanOrEqualTo(now()));

                // Rows arrive oldest-first, so a later row of equal standing
                // legitimately replaces an earlier one — that is the
                // latest('valid_from') the two original queries expressed.
                if ($current === null || $isValid || ! $currentIsValid) {
                    $best[$key] = $resolution;
                }
            });

        return $best;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(?string $search): array
    {
        // Filtering moved into the query: the previous version pulled every
        // active course into PHP and filtered the array afterwards, which at
        // 800 courses meant loading the whole catalog to export a handful.
        [$rows] = $this->assignmentPage(all: true, search: filled($search) ? $search : $this->search);

        return $rows;
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
