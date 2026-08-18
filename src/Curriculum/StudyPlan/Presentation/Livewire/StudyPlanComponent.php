<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Presentation\Livewire;

use App\Enums\PlanClassification;
use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use App\Models\Course as CourseModel;
use App\Models\Program;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Src\Curriculum\Course\Application\UseCases\ListCoursesUseCase;
use Src\Curriculum\Course\Domain\Entities\Course;
use Src\Curriculum\StudyPlan\Application\DTOs\CourseLinkDTO;
use Src\Curriculum\StudyPlan\Application\DTOs\LevelDTO;
use Src\Curriculum\StudyPlan\Application\DTOs\PrerequisiteDTO;
use Src\Curriculum\StudyPlan\Application\DTOs\StudyPlanStructureDTO;
use Src\Curriculum\StudyPlan\Application\UseCases\CreateStudyPlanUseCase;
use Src\Curriculum\StudyPlan\Application\UseCases\FindStudyPlanUseCase;
use Src\Curriculum\StudyPlan\Application\UseCases\GetActiveStudentCountsUseCase;
use Src\Curriculum\StudyPlan\Application\UseCases\ListStudyPlansUseCase;
use Src\Curriculum\StudyPlan\Application\UseCases\SaveStudyPlanStructureUseCase;
use Src\Curriculum\StudyPlan\Application\UseCases\UpdateStudyPlanUseCase;
use Src\Curriculum\StudyPlan\Domain\Entities\CourseLink;
use Src\Curriculum\StudyPlan\Domain\Entities\Level;
use Src\Curriculum\StudyPlan\Domain\Entities\Prerequisite;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteCourseNotLinkedToPlanException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteCoursesMustDifferException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\TerminalPlanRequiresClosingDateException;
use Src\Curriculum\StudyPlan\Presentation\Livewire\Forms\StudyPlanForm;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One Livewire component for the whole StudyPlan aggregate, matching the
 * spec's "one Livewire component per aggregate": the catalog table (create/
 * edit basic fields) and the structure editor (levels/course links/
 * prerequisites) are two sub-views of this same component, switched by
 * whether $viewingPlanId is set — not two separate routes.
 */
#[Layout('layouts.dashboard', ['title' => 'Study Plans', 'subtitle' => 'Levels, courses and prerequisites per plan'])]
class StudyPlanComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    protected string $tableMode = 'client';

    public bool $showModal = false;

    /**
     * Null while creating; the row's id while editing. Also read by
     * StudyPlanForm::rules() to scope the name-uniqueness check correctly.
     */
    public ?int $editingId = null;

    public StudyPlanForm $form;

    /**
     * Non-null switches render() into the structure sub-view for this plan.
     */
    public ?int $viewingPlanId = null;

    /**
     * @var array<int, array{key: string, id: ?int, number: int, courses: array<int, array{course_id: int, credits: int}>}>
     */
    public array $structureLevels = [];

    /**
     * @var array<int, array{key: string, required_course_id: int, dependent_course_id: int}>
     */
    public array $structurePrerequisites = [];

    public string $newLevelNumber = '';

    /**
     * Per-level "add course" input, keyed by the level's `key`.
     *
     * @var array<string, array{course_id: ?int, credits: ?int}>
     */
    public array $newCourseSelection = [];

    public string $newPrerequisiteRequired = '';

    public string $newPrerequisiteDependent = '';

    /**
     * Narrows the add-a-course picker. Empty shows the first
     * COURSE_PICKER_LIMIT courses by code rather than all ~800.
     */
    public string $courseSearch = '';

    /**
     * How many courses the picker offers at once. Large enough to browse,
     * small enough that the payload stays negligible.
     */
    private const COURSE_PICKER_LIMIT = 50;

    public function mount(): void
    {
        $this->authorize('viewAny', StudyPlan::class);
        $this->sortKey = 'name';
    }

    // ---------------------------------------------------------------
    // Catalog: basic-fields create/edit
    // ---------------------------------------------------------------

    public function openCreateModal(): void
    {
        $this->authorize('create', StudyPlan::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindStudyPlanUseCase $useCase): void
    {
        $studyPlan = $useCase->handle($id);

        $this->authorize('update', $studyPlan);

        $this->editingId = $id;
        $this->form->fromEntity($studyPlan);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateStudyPlanUseCase $createUseCase, UpdateStudyPlanUseCase $updateUseCase, ListStudyPlansUseCase $listUseCase, FindStudyPlanUseCase $findUseCase): void
    {
        $this->form->validate();

        try {
            if ($this->editingId === null) {
                $this->authorize('create', StudyPlan::class);
                $createUseCase->handle($this->form->toDto());
            } else {
                // authorize() needs an actual StudyPlan instance: Gate does
                // not forward a bare class-string as the policy method's
                // second argument, and update() requires one.
                $this->authorize('update', $findUseCase->handle($this->editingId));
                $updateUseCase->handle($this->editingId, $this->form->toDto());
            }
        } catch (TerminalPlanRequiresClosingDateException $e) {
            $this->dispatch('toast', variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: $this->editingId === null
            ? __('Study plan created.')
            : __('Study plan updated.'));
    }

    // ---------------------------------------------------------------
    // Structure sub-view
    // ---------------------------------------------------------------

    public function viewStructure(int $id, FindStudyPlanUseCase $useCase): void
    {
        $studyPlan = $useCase->handle($id);

        $this->authorize('view', $studyPlan);

        $this->viewingPlanId = $id;
        $this->loadStructureState($studyPlan);
    }

    public function backToCatalog(): void
    {
        $this->viewingPlanId = null;
        $this->structureLevels = [];
        $this->structurePrerequisites = [];
    }

    public function addLevel(): void
    {
        $number = (int) $this->newLevelNumber;

        if ($number < 1) {
            return;
        }

        $this->structureLevels[] = [
            'key' => 'new-'.Str::random(8),
            'id' => null,
            'number' => $number,
            'courses' => [],
        ];

        $this->newLevelNumber = '';
    }

    public function removeLevel(string $levelKey): void
    {
        $this->structureLevels = array_values(array_filter(
            $this->structureLevels,
            fn (array $level) => $level['key'] !== $levelKey,
        ));

        // Removing a level can orphan a prerequisite that referenced one of
        // its courses. That is not silently cleaned up here: saveStructure()
        // re-validates every prerequisite against the new level set and
        // rejects the save if one is now dangling, surfacing the exact
        // domain exception instead of quietly dropping it.
    }

    public function addCourseToLevel(string $levelKey): void
    {
        $selection = $this->newCourseSelection[$levelKey] ?? null;

        if (! $selection || ! filled($selection['course_id'] ?? null) || ! filled($selection['credits'] ?? null)) {
            return;
        }

        $courseId = (int) $selection['course_id'];
        $credits = (int) $selection['credits'];

        foreach ($this->structureLevels as &$level) {
            if ($level['key'] !== $levelKey) {
                continue;
            }

            $level['courses'] = array_values(array_filter(
                $level['courses'],
                fn (array $link) => $link['course_id'] !== $courseId,
            ));
            $level['courses'][] = ['course_id' => $courseId, 'credits' => $credits];
        }
        unset($level);

        $this->newCourseSelection[$levelKey] = ['course_id' => null, 'credits' => null];
    }

    public function removeCourseFromLevel(string $levelKey, int $courseId): void
    {
        foreach ($this->structureLevels as &$level) {
            if ($level['key'] !== $levelKey) {
                continue;
            }

            $level['courses'] = array_values(array_filter(
                $level['courses'],
                fn (array $link) => $link['course_id'] !== $courseId,
            ));
        }
        unset($level);
    }

    public function addPrerequisite(): void
    {
        if (! filled($this->newPrerequisiteRequired) || ! filled($this->newPrerequisiteDependent)) {
            return;
        }

        $required = (int) $this->newPrerequisiteRequired;
        $dependent = (int) $this->newPrerequisiteDependent;

        if ($required === $dependent) {
            $this->dispatch('toast', variant: 'danger', text: __('A course cannot be a prerequisite of itself.'));

            return;
        }

        $key = "{$required}-{$dependent}";

        $alreadyPresent = collect($this->structurePrerequisites)->contains(fn (array $p) => $p['key'] === $key);

        if (! $alreadyPresent) {
            $this->structurePrerequisites[] = [
                'key' => $key,
                'required_course_id' => $required,
                'dependent_course_id' => $dependent,
            ];
        }

        $this->newPrerequisiteRequired = '';
        $this->newPrerequisiteDependent = '';
    }

    public function removePrerequisite(string $key): void
    {
        $this->structurePrerequisites = array_values(array_filter(
            $this->structurePrerequisites,
            fn (array $p) => $p['key'] !== $key,
        ));
    }

    public function saveStructure(SaveStudyPlanStructureUseCase $useCase, FindStudyPlanUseCase $findUseCase): void
    {
        $current = $findUseCase->handle($this->viewingPlanId);
        $this->authorize('update', $current);

        $levelDtos = array_map(
            fn (array $level) => new LevelDTO(
                number: (int) $level['number'],
                courseLinks: array_map(
                    fn (array $link) => new CourseLinkDTO((int) $link['course_id'], (int) $link['credits']),
                    $level['courses'],
                ),
            ),
            $this->structureLevels,
        );

        $prerequisiteDtos = array_map(
            fn (array $p) => new PrerequisiteDTO((int) $p['required_course_id'], (int) $p['dependent_course_id']),
            $this->structurePrerequisites,
        );

        try {
            $updated = $useCase->handle($this->viewingPlanId, new StudyPlanStructureDTO($levelDtos, $prerequisiteDtos));
        } catch (PrerequisiteCourseNotLinkedToPlanException|PrerequisiteCoursesMustDifferException $e) {
            $this->dispatch('toast', variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->loadStructureState($updated);
        $this->dispatch('toast', variant: 'success', text: __('Study plan structure saved.'));
    }

    private function loadStructureState(StudyPlan $studyPlan): void
    {
        $this->structureLevels = array_map(
            fn (Level $level) => [
                'key' => 'level-'.($level->id() ?? Str::random(8)),
                'id' => $level->id(),
                'number' => $level->number(),
                'courses' => array_map(
                    fn (CourseLink $link) => ['course_id' => $link->courseId, 'credits' => $link->credits],
                    $level->courseLinks(),
                ),
            ],
            $studyPlan->levels(),
        );

        $this->structurePrerequisites = array_map(
            fn (Prerequisite $p) => [
                'key' => "{$p->requiredCourseId()}-{$p->dependentCourseId()}",
                'required_course_id' => $p->requiredCourseId(),
                'dependent_course_id' => $p->dependentCourseId(),
            ],
            $studyPlan->prerequisites(),
        );
    }

    // ---------------------------------------------------------------
    // Exports (catalog only — the structure sub-view has no export)
    // ---------------------------------------------------------------

    public function exportPdf(ListStudyPlansUseCase $useCase, ?string $search = null): void
    {
        $this->authorize('exportPdf', StudyPlan::class);

        $this->queuePdf(
            __('Study Plans'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Study Plans')).'.pdf',
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListStudyPlansUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', StudyPlan::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Study Plans')).'.xlsx',
            $exporter,
        );
    }

    // ---------------------------------------------------------------
    // Rendering
    // ---------------------------------------------------------------

    public function render(
        ListStudyPlansUseCase $useCase,
        FindStudyPlanUseCase $findUseCase,
        GetActiveStudentCountsUseCase $countsUseCase,
        ListCoursesUseCase $coursesUseCase,
    ): View {
        if ($this->viewingPlanId !== null) {
            return $this->renderStructureView($findUseCase, $countsUseCase, $coursesUseCase);
        }

        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);

        return $view->with('programOptions', $this->programOptions());
    }

    private function renderStructureView(
        FindStudyPlanUseCase $findUseCase,
        GetActiveStudentCountsUseCase $countsUseCase,
        ListCoursesUseCase $coursesUseCase,
    ): View {
        return view('curriculum.study-plan.livewire.study-plan-structure', [
            'studyPlan' => $findUseCase->handle($this->viewingPlanId),
            'activeStudentCounts' => $countsUseCase->handle($this->viewingPlanId),
            // Two distinct sets on purpose — see courseOptions() and
            // planCourseOptions() for why the full catalog is the wrong answer
            // for the lookups and for the prerequisite pickers.
            'courseOptions' => $this->courseOptions($coursesUseCase),
            'planCourseOptions' => $this->planCourseOptions(),
        ]);
    }

    private function renderClientMode(ListStudyPlansUseCase $useCase): View
    {
        return view('curriculum.study-plan.livewire.study-plan-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
        ]);
    }

    private function renderServerMode(ListStudyPlansUseCase $useCase): View
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

        return view('curriculum.study-plan.livewire.study-plan-component', [
            'tableMode' => 'server',
            'studyPlans' => $paginator,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(StudyPlan $studyPlan): array
    {
        return [
            'id' => $studyPlan->id(),
            'name' => $studyPlan->name(),
            'implementationYear' => $studyPlan->implementationYear(),
            'classification' => $studyPlan->classification()->value,
            'isTerminal' => $studyPlan->classification() === PlanClassification::Terminal,
            'enrollmentClosingDate' => $studyPlan->enrollmentClosingDate()?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListStudyPlansUseCase $useCase): array
    {
        return array_map($this->toRow(...), $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListStudyPlansUseCase $useCase, ?string $search): array
    {
        $candidate = filled($search) ? $search : $this->search;

        return array_map(
            $this->toRow(...),
            $useCase->all(
                search: $candidate !== '' ? $candidate : null,
                sortBy: $this->sortKey,
                sortDir: $this->sortDir,
            ),
        );
    }

    /**
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'implementationYear', 'label' => __('Implementation year')],
            ['key' => 'classification', 'label' => __('Classification')],
            ['key' => 'enrollmentClosingDate', 'label' => __('Enrollment closing date'), 'format' => fn (?string $v): string => $v ?? '—'],
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

    /**
     * Catalog for the "add a course to this level" picker. Cross-aggregate
     * read within the same Curriculum bounded context — same pragmatic
     * coupling RoleComponent already uses to read the Permission catalog.
     *
     * Capped and searchable rather than exhaustive: this used to render every
     * active course, ~800 at the target volume, on every render of the
     * structure sub-view. A dropdown with 800 entries is not usable anyway, so
     * the fix is the same in both directions — type to narrow it down.
     *
     * paginate() is used instead of all() so the cap happens in SQL. It is an
     * existing repository method with a stable signature; no contract changes.
     *
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function courseOptions(ListCoursesUseCase $useCase): array
    {
        $result = $useCase->paginate(
            search: $this->courseSearch !== '' ? $this->courseSearch : null,
            perPage: self::COURSE_PICKER_LIMIT,
            page: 1,
            sortBy: 'code',
        );

        return array_map(
            static fn (Course $course) => ['id' => $course->id(), 'code' => $course->code(), 'name' => $course->name()],
            $result['items'],
        );
    }

    /**
     * The courses this plan already links, for two jobs the full catalog was
     * doing badly:
     *
     * - resolving a code and name for rows already on screen, which only ever
     *   needs the plan's own courses;
     * - populating the prerequisite pickers. Those should never have offered
     *   the whole catalog: the domain rejects a prerequisite pointing at a
     *   course outside the plan (PrerequisiteCourseNotLinkedToPlanException),
     *   so offering one was inviting a guaranteed rejection.
     *
     * ~80 rows instead of ~800, and a narrower, more correct choice.
     *
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function planCourseOptions(): array
    {
        $ids = [];

        foreach ($this->structureLevels as $level) {
            foreach ($level['courses'] as $link) {
                $ids[] = (int) $link['course_id'];
            }
        }

        foreach ($this->structurePrerequisites as $prerequisite) {
            $ids[] = (int) $prerequisite['required_course_id'];
            $ids[] = (int) $prerequisite['dependent_course_id'];
        }

        if ($ids === []) {
            return [];
        }

        return CourseModel::query()
            ->whereIn('id', array_unique($ids))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(static fn (CourseModel $course) => [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
            ])
            ->all();
    }
}
