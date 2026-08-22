<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Presentation\Livewire;

use App\Enums\LaboratoryType;
use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use App\Models\Modality as ModalityModel;
use App\Models\Program;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Src\Curriculum\Course\Application\UseCases\ActivateCourseUseCase;
use Src\Curriculum\Course\Application\UseCases\CreateCourseUseCase;
use Src\Curriculum\Course\Application\UseCases\DeactivateCourseUseCase;
use Src\Curriculum\Course\Application\UseCases\FindCourseUseCase;
use Src\Curriculum\Course\Application\UseCases\ListCoursesUseCase;
use Src\Curriculum\Course\Application\UseCases\UpdateCourseUseCase;
use Src\Curriculum\Course\Domain\Entities\Course;
use Src\Curriculum\Course\Presentation\Livewire\Forms\CourseForm;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.dashboard', ['title' => 'Courses', 'subtitle' => 'Course catalog shared across study plans'])]
class CourseComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /**
     * Course catalog is expected to stay small-to-medium (a program's full
     * course list, not a per-plan-instance record) — client-side by
     * default, same reasoning as Role/Permission.
     */
    protected string $tableMode = 'server';

    public bool $showModal = false;

    /**
     * Null while creating; the row's id while editing. Also read by
     * CourseForm::rules() to scope the code-uniqueness check correctly.
     */
    public ?int $editingId = null;

    /**
     * The course's current modality id while editing, read-only display
     * only (RC-03) — never bound to CourseForm, since reassigning modality
     * is exclusively the gated /modality-assignments flow's job.
     */
    public ?int $editingModalityId = null;

    public CourseForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Course::class);
        $this->sortKey = 'code';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Course::class);

        $this->editingId = null;
        $this->editingModalityId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindCourseUseCase $useCase): void
    {
        $course = $useCase->handle($id);

        $this->authorize('update', $course);

        $this->editingId = $id;
        $this->editingModalityId = $course->modalityId();
        $this->form->fromEntity($course);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateCourseUseCase $createUseCase, UpdateCourseUseCase $updateUseCase, ListCoursesUseCase $listUseCase, FindCourseUseCase $findUseCase): void
    {
        $this->form->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Course::class);
            $createUseCase->handle($this->form->toDto());
        } else {
            $this->authorize('update', $findUseCase->handle($this->editingId));
            $updateUseCase->handle($this->editingId, $this->form->toDto());
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: $this->editingId === null
            ? __('Course created.')
            : __('Course updated.'));
    }

    /**
     * "Delete" here is always a deactivation (flips `active`), never a row
     * delete — a course can be linked to plans that must keep referencing
     * it historically. Reuses the same <x-ui.confirm-delete-modal> flow as
     * every other CRUD in this app; only the copy in the Blade view differs.
     */
    public function delete(int $id, DeactivateCourseUseCase $useCase, ListCoursesUseCase $listUseCase, FindCourseUseCase $findUseCase): void
    {
        // authorize() needs an actual Course instance here, not the class
        // name: CoursePolicy::delete(User $user, Course $course) has a
        // required second parameter, and Gate does not forward a bare
        // class-string as that argument — it only passes $user, which
        // throws "too few arguments" the moment this action is exercised.
        $this->authorize('delete', $findUseCase->handle($id));

        $useCase->handle($id);

        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: __('Course deactivated.'));
    }

    /**
     * Reverses a prior `delete` (deactivation): flips the course's `active`
     * flag back on. Never creates a new row — the course was never deleted.
     */
    public function activate(int $id, ActivateCourseUseCase $useCase, ListCoursesUseCase $listUseCase, FindCourseUseCase $findUseCase): void
    {
        $this->authorize('activate', $findUseCase->handle($id));

        $useCase->handle($id);

        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: __('Course activated.'));
    }

    public function exportPdf(ListCoursesUseCase $useCase, ?string $search = null): void
    {
        $this->authorize('exportPdf', Course::class);

        $this->queuePdfExport(
            __('Courses'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Courses')).'.pdf',
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListCoursesUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Course::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Courses')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListCoursesUseCase $useCase): View
    {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);

        return $view
            ->with('programOptions', $this->programOptions())
            ->with('laboratoryTypeOptions', LaboratoryType::cases())
            ->with('modalityNames', $this->modalityNames());
    }

    private function renderClientMode(ListCoursesUseCase $useCase): View
    {
        return view('curriculum.course.livewire.course-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
        ]);
    }

    private function renderServerMode(ListCoursesUseCase $useCase): View
    {
        $result = $useCase->paginate(
            search: $this->search !== '' ? $this->search : null,
            perPage: $this->perPage,
            page: $this->page,
            sortBy: $this->sortKey,
            sortDir: $this->sortDir,
        );

        $paginator = new LengthAwarePaginator(
            items: $result['items'],
            total: $result['total'],
            perPage: $this->perPage,
            currentPage: $this->page,
        );

        return view('curriculum.course.livewire.course-component', [
            'tableMode' => 'server',
            'courses' => $paginator,
        ]);
    }

    /**
     * Plain-array projection handed to Alpine as JSON — keeps the Domain
     * Entity from ever leaking past the Presentation boundary. Modality is
     * read-only here (RC-03): reassigning it happens exclusively through
     * the gated /modality-assignments flow, never this component's own
     * create/edit modal (see CreateCourseUseCase/UpdateCourseUseCase).
     *
     * @param  array<int, string>  $modalityNames  id => name, pragmatic
     *                                             cross-aggregate read, same coupling EquivalencyComponent
     *                                             already uses for course codes.
     * @return array<string, mixed>
     */
    private function toRow(Course $course, array $modalityNames): array
    {
        return [
            'id' => $course->id(),
            'code' => $course->code(),
            'name' => $course->name(),
            'isService' => $course->isService(),
            'isBottleneck' => $course->isBottleneck(),
            'requiresLaboratory' => $course->requiresLaboratory(),
            'active' => $course->isActive(),
            'modalityName' => $course->modalityId() !== null ? ($modalityNames[$course->modalityId()] ?? null) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListCoursesUseCase $useCase): array
    {
        $modalityNames = $this->modalityNames();

        return array_map(
            fn (Course $course) => $this->toRow($course, $modalityNames),
            $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListCoursesUseCase $useCase, ?string $search): array
    {
        $candidate = filled($search) ? $search : $this->search;
        $modalityNames = $this->modalityNames();

        return array_map(
            fn (Course $course) => $this->toRow($course, $modalityNames),
            $useCase->all(
                search: $candidate !== '' ? $candidate : null,
                sortBy: $this->sortKey,
                sortDir: $this->sortDir,
            ),
        );
    }

    /**
     * @return array<int, string>
     */
    private function modalityNames(): array
    {
        return ModalityModel::query()->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'isService', 'label' => __('Service course'), 'format' => fn (bool $v): string => $v ? __('Yes') : __('No')],
            ['key' => 'modalityName', 'label' => __('Modality')],
            ['key' => 'active', 'label' => __('Status'), 'format' => fn (bool $v): string => $v ? __('Active') : __('Inactive')],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function programOptions(): array
    {
        return Program::query()->active()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Program $program) => ['id' => $program->id, 'name' => $program->name])
            ->all();
    }
}
