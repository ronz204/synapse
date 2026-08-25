<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithCourseSearch;
use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use App\Models\Course as CourseModel;
use App\Models\Equivalency as EquivalencyModel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Src\Curriculum\Equivalency\Application\UseCases\FindEquivalencyUseCase;
use Src\Curriculum\Equivalency\Application\UseCases\GetEquivalencyDocumentUseCase;
use Src\Curriculum\Equivalency\Application\UseCases\ListEquivalenciesUseCase;
use Src\Curriculum\Equivalency\Application\UseCases\RegisterEquivalencyUseCase;
use Src\Curriculum\Equivalency\Application\UseCases\ResolveEquivalencyContradictionUseCase;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\Exceptions\CycleDetectedException;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyContradictionException;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyDocumentNotFoundException;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyDocumentRequiredException;
use Src\Curriculum\Equivalency\Presentation\Livewire\Forms\EquivalencyForm;
use Src\Shared\Document\Contracts\AttachableDocument;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.dashboard', ['title' => 'Equivalencies', 'subtitle' => 'Cross-plan course equivalencies and their integrity checks'])]
class EquivalencyComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithCourseSearch;
    use InteractsWithDataTable;
    use InteractsWithExports;
    use WithFileUploads;

    protected string $tableMode = 'server';

    public bool $showModal = false;

    /**
     * Text typed into the source/target course pickers — drives
     * <x-ui.course-combobox>'s search, re-narrowed on every keystroke via
     * searchActiveCourses() rather than filtering a full course list
     * client-side. Set to "CODE — Name" once a course is picked (see
     * selectSourceCourse()/selectTargetCourse()) so the input reflects the
     * current selection instead of going blank.
     */
    public string $sourceCourseSearch = '';

    public string $targetCourseSearch = '';

    public EquivalencyForm $form;

    /**
     * Non-null switches the modal into the contradiction-resolution panel
     * for this existing equivalency, instead of the plain registration form.
     * Set by register() when RegisterEquivalencyUseCase reports a
     * contradiction; the form's own state (already filled in by the user)
     * is deliberately left untouched so resolveContradiction() can rebuild
     * the exact same candidate from it.
     */
    public ?int $conflictingEquivalencyId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Equivalency::class);
        $this->sortKey = 'created_at';
        $this->sortDir = 'desc';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Equivalency::class);

        $this->form->reset();
        $this->sourceCourseSearch = '';
        $this->targetCourseSearch = '';
        $this->conflictingEquivalencyId = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function selectSourceCourse(int $courseId, string $code, string $name): void
    {
        $this->form->sourceCourseId = $courseId;
        $this->sourceCourseSearch = "{$code} — {$name}";
    }

    public function selectTargetCourse(int $courseId, string $code, string $name): void
    {
        $this->form->targetCourseId = $courseId;
        $this->targetCourseSearch = "{$code} — {$name}";
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->conflictingEquivalencyId = null;
    }

    public function register(RegisterEquivalencyUseCase $useCase, ListEquivalenciesUseCase $listUseCase): void
    {
        $this->form->validate();
        $this->authorize('create', Equivalency::class);

        $document = $this->buildDocumentFromUpload();

        try {
            $useCase->handle($this->form->toDto($document));
        } catch (CycleDetectedException $e) {
            Flux::toast(variant: 'danger', text: __('Cannot save: this would close a cycle (:chain).', ['chain' => $e->chainLabel()]));

            return;
        } catch (EquivalencyContradictionException $e) {
            $this->conflictingEquivalencyId = $e->existingEquivalencyId();

            return;
        } catch (EquivalencyDocumentRequiredException $e) {
            $this->addError('form.document', __($e->getMessage()));

            return;
        }

        $this->showModal = false;
        $this->conflictingEquivalencyId = null;
        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: __('Equivalency registered.'));
    }

    /**
     * Rebuilds the candidate straight from the form's own still-populated
     * state (never reset after register() detected a contradiction) plus
     * the conflicting record's id already held in $conflictingEquivalencyId
     * — this is what lets the candidate be persisted, win or lose, without
     * ever having been discarded.
     */
    public function resolveContradiction(string $winner, ResolveEquivalencyContradictionUseCase $useCase, ListEquivalenciesUseCase $listUseCase, FindEquivalencyUseCase $findUseCase): void
    {
        $existingId = $this->conflictingEquivalencyId;

        if ($existingId === null) {
            return;
        }

        // authorize() needs an actual Equivalency instance here, not the
        // class name: EquivalencyPolicy::resolveContradiction() has a
        // required second parameter, and Gate does not forward a bare
        // class-string as that argument.
        $this->authorize('resolveContradiction', $findUseCase->handle($existingId));

        $document = $this->buildDocumentFromUpload();

        try {
            $useCase->handle($this->form->toDto($document), $existingId, $winner === 'candidate');
        } catch (EquivalencyDocumentRequiredException $e) {
            $this->addError('form.document', __($e->getMessage()));

            return;
        }

        $this->showModal = false;
        $this->conflictingEquivalencyId = null;
        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: $winner === 'candidate'
            ? __('New resolution registered; the previous one is now Superseded.')
            : __('Existing resolution kept; the new submission is now Superseded.'));
    }

    /**
     * Gated on `view`, same as the catalog itself — anyone who can see an
     * equivalency's row can inspect the resolution that backs it, which is
     * the whole point of an auditable trail.
     *
     * Not every equivalency has a document, though: RegisterEquivalencyUseCase
     * enforces one on every write it handles, but a row inserted by another
     * path (bulk seed/import data) can exist without one. Rather than let
     * that surface as a raw exception, this checks first and reports it as
     * a toast — nothing is streamed to the browser when there's nothing to
     * send.
     */
    public function downloadDocument(int $equivalencyId, FindEquivalencyUseCase $findUseCase, GetEquivalencyDocumentUseCase $documentUseCase): ?StreamedResponse
    {
        $this->authorize('view', $findUseCase->handle($equivalencyId));

        try {
            $document = $documentUseCase->handle($equivalencyId);
        } catch (EquivalencyDocumentNotFoundException) {
            Flux::toast(variant: 'danger', text: __('This equivalency has no resolution document attached — there is nothing to download.'));

            return null;
        }

        Flux::toast(variant: 'success', text: __('Resolution document ready — download starting.'));

        return Storage::disk($document->disk)->download($document->path, $document->originalName);
    }

    /**
     * Reads the still-temporary upload's metadata (safe, read-only) without
     * moving it anywhere permanent — see AttachableDocument's docblock for
     * why the actual move happens later, inside the use case's own
     * persistence step, only once cycle/contradiction checks have passed.
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

    public function exportPdf(ListEquivalenciesUseCase $useCase, ?string $search = null): void
    {
        $this->authorize('exportPdf', Equivalency::class);

        $this->queuePdfExport(
            __('Equivalencies'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Equivalencies')).'.pdf',
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListEquivalenciesUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Equivalency::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Equivalencies')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListEquivalenciesUseCase $useCase, FindEquivalencyUseCase $findUseCase): View
    {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);

        return $view
            ->with('sourceCourseOptions', $this->searchActiveCourses($this->sourceCourseSearch))
            ->with('targetCourseOptions', $this->searchActiveCourses($this->targetCourseSearch))
            ->with('conflictingEquivalency', $this->conflictingEquivalencyId !== null ? $findUseCase->handle($this->conflictingEquivalencyId) : null);
    }

    private function renderClientMode(ListEquivalenciesUseCase $useCase): View
    {
        return view('curriculum.equivalency.livewire.equivalency-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
        ]);
    }

    private function renderServerMode(ListEquivalenciesUseCase $useCase): View
    {
        $result = $useCase->paginate(
            search: $this->search !== '' ? $this->search : null,
            perPage: $this->perPage,
            page: $this->page,
            sortBy: $this->sortKey,
            sortDir: $this->sortDir,
        );

        $paginator = new LengthAwarePaginator(
            items: $this->rowsFor($result['items']),
            total: $result['total'],
            perPage: $this->perPage,
            currentPage: $this->page,
        );

        return view('curriculum.equivalency.livewire.equivalency-component', [
            'tableMode' => 'server',
            'equivalencies' => $paginator,
        ]);
    }

    /**
     * Plain-array projection handed to Alpine (client mode) or the
     * server-mode Blade branch alike — keeps the Domain Entity from ever
     * leaking past the Presentation boundary. Course codes and the
     * superseding resolution's number are resolved once for the whole
     * batch by the caller ($courseCodes/$supersededNumbers), never queried
     * per row — see rowsFor().
     *
     * @param  array<int, string>  $courseCodes  courseId => code
     * @param  array<int, string>  $supersededNumbers  equivalencyId => resolution_number
     * @return array<string, mixed>
     */
    private function toRow(Equivalency $equivalency, array $courseCodes, array $supersededNumbers): array
    {
        return [
            'id' => $equivalency->id(),
            // Both ids always resolve: the FK is restrictOnDelete, so a
            // course referenced by an equivalency can never be missing.
            'sourceCourseCode' => $courseCodes[$equivalency->sourceCourseId()],
            'targetCourseCode' => $courseCodes[$equivalency->targetCourseId()],
            // Translated here rather than left as the raw enum case name:
            // this array feeds both the client-mode Alpine table (JSON, no
            // Blade __() available) and the PDF/Excel export, so the
            // translation has to happen once at the source, not per-view.
            'direction' => __(Str::headline($equivalency->direction()->name)),
            'resolutionNumber' => $equivalency->resolutionNumber(),
            'status' => __($equivalency->status()->name),
            'isActive' => $equivalency->isActive(),
            'supersededById' => $equivalency->supersededById(),
            // Surfaces which resolution actually prevailed, so a Superseded
            // row reads as an auditable trail instead of a dead end.
            'supersededByResolutionNumber' => $equivalency->supersededById() !== null
                ? ($supersededNumbers[$equivalency->supersededById()] ?? null)
                : null,
        ];
    }

    /**
     * Batches the two lookups `toRow()` used to run per equivalency (course
     * codes, superseding resolution numbers) into one query each for the
     * whole batch — a page's worth in server mode, or the whole search
     * result in client mode/export — instead of 1-2 extra queries per row.
     *
     * @param  array<int, Equivalency>  $equivalencies
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(array $equivalencies): array
    {
        if ($equivalencies === []) {
            return [];
        }

        $courseIds = collect($equivalencies)
            ->flatMap(fn (Equivalency $equivalency) => [$equivalency->sourceCourseId(), $equivalency->targetCourseId()])
            ->unique()
            ->all();

        $courseCodes = CourseModel::query()->whereIn('id', $courseIds)->pluck('code', 'id')->all();

        $supersededByIds = collect($equivalencies)
            ->map(fn (Equivalency $equivalency) => $equivalency->supersededById())
            ->filter()
            ->unique()
            ->all();

        $supersededNumbers = $supersededByIds === []
            ? []
            : EquivalencyModel::query()->whereIn('id', $supersededByIds)->pluck('resolution_number', 'id')->all();

        return array_map(
            fn (Equivalency $equivalency) => $this->toRow($equivalency, $courseCodes, $supersededNumbers),
            $equivalencies,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListEquivalenciesUseCase $useCase): array
    {
        return $this->rowsFor($useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListEquivalenciesUseCase $useCase, ?string $search): array
    {
        $candidate = filled($search) ? $search : $this->search;

        return $this->rowsFor($useCase->all(
            search: $candidate !== '' ? $candidate : null,
            sortBy: $this->sortKey,
            sortDir: $this->sortDir,
        ));
    }

    /**
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'sourceCourseCode', 'label' => __('Source course')],
            ['key' => 'targetCourseCode', 'label' => __('Target course')],
            ['key' => 'direction', 'label' => __('Direction')],
            ['key' => 'resolutionNumber', 'label' => __('Resolution number')],
            ['key' => 'status', 'label' => __('Status')],
        ];
    }
}
