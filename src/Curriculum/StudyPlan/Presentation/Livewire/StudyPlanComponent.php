<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Presentation\Livewire;

use App\Enums\PlanClassification;
use App\Livewire\Concerns\InteractsWithCourseSearch;
use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use App\Models\Course as CourseModel;
use App\Models\Program;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
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
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteRequiredCourseMustBeInEarlierLevelException;
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
    use InteractsWithCourseSearch;
    use InteractsWithDataTable;
    use InteractsWithExports;

    protected string $tableMode = 'server';

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

    /**
     * Which level's card the structure sub-view currently displays — levels
     * are paginated one-at-a-time (see the level pager in the structure
     * view) rather than stacked, so a plan with many levels never forces a
     * long vertical scroll. Null only when the plan has no levels yet.
     */
    public ?string $activeLevelKey = null;

    /**
     * Per-level "add course" input, keyed by the level's `key`.
     *
     * @var array<string, array{course_id: ?int, credits: ?int}>
     */
    public array $newCourseSelection = [];

    /**
     * Per-level text typed into <x-ui.course-combobox>'s search, keyed by
     * the same level `key` as $newCourseSelection — see
     * App\Livewire\Concerns\InteractsWithCourseSearch for why this replaced
     * a full course list per level's <select>.
     *
     * @var array<string, string>
     */
    public array $newCourseSearchByLevel = [];

    /**
     * Which course's inline prerequisite panel is expanded, by course id —
     * a course can only be linked once per plan in practice, so the course
     * id alone (not a level-scoped key) identifies the panel regardless of
     * which level's card it's rendered under.
     */
    public ?int $expandedPrerequisiteCourseId = null;

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
        } catch (TerminalPlanRequiresClosingDateException) {
            // The domain exception's own message() stays English-only by
            // design (the domain layer must not depend on Laravel's
            // translator) — the presentation layer maps the exception type
            // to a translated, user-facing message instead of relaying it.
            Flux::toast(variant: 'danger', text: __('A study plan classified as Terminal must have an enrollment closing date.'));

            return;
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: $this->editingId === null
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
        $this->newCourseSearchByLevel = [];
        $this->activeLevelKey = null;
        $this->expandedPrerequisiteCourseId = null;
    }

    /**
     * The next level's number is always inferred from the last one (highest
     * `number` currently in the structure, plus one — or 1 for the first
     * level) rather than typed in: levels are added in order in practice,
     * and asking for a number the user would almost always just count up
     * anyway is friction with no payoff.
     */
    public function addLevel(): void
    {
        $key = 'new-'.Str::random(8);

        $this->structureLevels[] = [
            'key' => $key,
            'id' => null,
            'number' => $this->nextLevelNumber(),
            'courses' => [],
        ];

        $this->sortLevelsByNumber();

        // Jump the pager to the level just added so it's immediately
        // visible instead of silently landing off-screen on another page.
        $this->activeLevelKey = $key;
        Flux::toast(variant: 'success', text: __('Level added.'));
    }

    public function nextLevelNumber(): int
    {
        return (int) (collect($this->structureLevels)->max('number') ?? 0) + 1;
    }

    public function removeLevel(string $levelKey): void
    {
        $this->structureLevels = array_values(array_filter(
            $this->structureLevels,
            fn (array $level) => $level['key'] !== $levelKey,
        ));

        if ($this->activeLevelKey === $levelKey) {
            $this->activeLevelKey = $this->structureLevels[0]['key'] ?? null;
        }

        // Removing a level can orphan a prerequisite that referenced one of
        // its courses. That is not silently cleaned up here: saveStructure()
        // re-validates every prerequisite against the new level set and
        // rejects the save if one is now dangling, surfacing the exact
        // domain exception instead of quietly dropping it.
        Flux::toast(variant: 'success', text: __('Level removed.'));
    }

    public function goToLevel(string $levelKey): void
    {
        $this->activeLevelKey = $levelKey;
    }

    /**
     * Levels are kept sorted by `number` at all times so the pager and the
     * "current level" tabs read in the order a user expects (1, 2, 3, ...)
     * regardless of the order levels were added or loaded in.
     */
    private function sortLevelsByNumber(): void
    {
        usort($this->structureLevels, fn (array $a, array $b) => $a['number'] <=> $b['number']);
    }

    public function addCourseToLevel(string $levelKey): void
    {
        $selection = $this->newCourseSelection[$levelKey] ?? null;

        if (! $selection || ! filled($selection['course_id'] ?? null)) {
            return;
        }

        // The credits field is a plain text input (no browser numeric
        // guard — see the "Credits" field's comment in the view for why),
        // so this validates what a <input type="number"> used to guarantee
        // for free: a whole, positive number, nothing else.
        $rawCredits = (string) ($selection['credits'] ?? '');

        if (! ctype_digit($rawCredits) || (int) $rawCredits < 1) {
            Flux::toast(variant: 'danger', text: __('Enter a valid number of credits.'));

            return;
        }

        $courseId = (int) $selection['course_id'];
        $credits = (int) $rawCredits;

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
        $this->newCourseSearchByLevel[$levelKey] = '';
        Flux::toast(variant: 'success', text: __('Course added to level.'));
    }

    public function selectCourseForLevel(string $levelKey, int $courseId, string $code, string $name): void
    {
        $this->newCourseSelection[$levelKey]['course_id'] = $courseId;
        $this->newCourseSearchByLevel[$levelKey] = "{$code} — {$name}";
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

        Flux::toast(variant: 'success', text: __('Course removed from level.'));
    }

    public function togglePrerequisitesFor(int $courseId): void
    {
        $this->expandedPrerequisiteCourseId = $this->expandedPrerequisiteCourseId === $courseId ? null : $courseId;
    }

    /**
     * Adds a prerequisite the course $dependentCourseId depends on. Picking
     * $requiredCourseId is itself the action — <x-ui.local-course-combobox>
     * calls this directly the moment a result is clicked, no separate
     * "confirm" step — and its option list is restricted to courses linked
     * to an earlier level of this plan (see linkedCourseInfo() and the
     * structure view's $availableRequiredOptions), so every value reaching
     * here already satisfies StudyPlan::addPrerequisite()'s invariants in
     * the normal flow. The same checks are repeated here regardless,
     * since this method is a public entry point on its own — not merely a
     * belt-and-suspenders duplicate of the UI filtering.
     */
    public function addPrerequisiteFor(int $dependentCourseId, int $requiredCourseId): void
    {
        if ($requiredCourseId === $dependentCourseId) {
            Flux::toast(variant: 'danger', text: __('A course cannot be a prerequisite of itself.'));

            return;
        }

        $requiredLevelNumber = $this->levelNumberForCourse($requiredCourseId);
        $dependentLevelNumber = $this->levelNumberForCourse($dependentCourseId);

        if ($requiredLevelNumber === null || $dependentLevelNumber === null || $requiredLevelNumber >= $dependentLevelNumber) {
            Flux::toast(variant: 'danger', text: __('A prerequisite course must belong to an earlier level of this plan than the course it is required for.'));

            return;
        }

        $key = "{$requiredCourseId}-{$dependentCourseId}";

        $alreadyPresent = collect($this->structurePrerequisites)->contains(fn (array $p) => $p['key'] === $key);

        if (! $alreadyPresent) {
            $this->structurePrerequisites[] = [
                'key' => $key,
                'required_course_id' => $requiredCourseId,
                'dependent_course_id' => $dependentCourseId,
            ];
            Flux::toast(variant: 'success', text: __('Prerequisite added.'));
        }
    }

    public function removePrerequisite(string $key): void
    {
        $this->structurePrerequisites = array_values(array_filter(
            $this->structurePrerequisites,
            fn (array $p) => $p['key'] !== $key,
        ));

        Flux::toast(variant: 'success', text: __('Prerequisite removed.'));
    }

    public function saveStructure(SaveStudyPlanStructureUseCase $useCase, FindStudyPlanUseCase $findUseCase): void
    {
        $current = $findUseCase->handle($this->viewingPlanId);
        $this->authorize('update', $current);
        $this->resetValidation('structurePrerequisites');

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
        } catch (PrerequisiteCourseNotLinkedToPlanException) {
            $message = __('A course used in a prerequisite must exist within the same study plan.');

            $this->addError('structurePrerequisites', $message);
            Flux::toast(variant: 'danger', text: $message);

            return;
        } catch (PrerequisiteCoursesMustDifferException) {
            Flux::toast(variant: 'danger', text: __('A course cannot be a prerequisite of itself.'));

            return;
        } catch (PrerequisiteRequiredCourseMustBeInEarlierLevelException) {
            $message = __('A prerequisite course must belong to an earlier level of this plan than the course it is required for.');

            $this->addError('structurePrerequisites', $message);
            Flux::toast(variant: 'danger', text: $message);

            return;
        }

        $this->loadStructureState($updated);
        Flux::toast(variant: 'success', text: __('Study plan structure saved.'));
    }

    /**
     * The level number the given course is currently linked to within the
     * in-memory structure being edited, or null if it isn't linked to any
     * level yet — mirrors StudyPlan::levelNumberForCourse() on the domain
     * side, but reads the component's own draft state rather than a
     * persisted aggregate, since a prerequisite can be added before the
     * structure is saved.
     */
    private function levelNumberForCourse(int $courseId): ?int
    {
        foreach ($this->structureLevels as $level) {
            foreach ($level['courses'] as $link) {
                if ($link['course_id'] === $courseId) {
                    return (int) $level['number'];
                }
            }
        }

        return null;
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

        $this->sortLevelsByNumber();
        $this->activeLevelKey = $this->structureLevels[0]['key'] ?? null;

        $this->structurePrerequisites = array_map(
            fn (Prerequisite $p) => [
                'key' => "{$p->requiredCourseId()}-{$p->dependentCourseId()}",
                'required_course_id' => $p->requiredCourseId(),
                'dependent_course_id' => $p->dependentCourseId(),
            ],
            $studyPlan->prerequisites(),
        );

        $this->newCourseSearchByLevel = [];
        $this->expandedPrerequisiteCourseId = null;
    }

    // ---------------------------------------------------------------
    // Exports (catalog only — the structure sub-view has no export)
    // ---------------------------------------------------------------

    public function exportPdf(ListStudyPlansUseCase $useCase, ?string $search = null): void
    {
        $this->authorize('exportPdf', StudyPlan::class);

        $this->queuePdfExport(
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
    ): View {
        if ($this->viewingPlanId !== null) {
            return $this->renderStructureView($findUseCase, $countsUseCase);
        }

        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);

        return $view->with('programOptions', $this->programOptions());
    }

    private function renderStructureView(
        FindStudyPlanUseCase $findUseCase,
        GetActiveStudentCountsUseCase $countsUseCase,
    ): View {
        return view('curriculum.study-plan.livewire.study-plan-structure', [
            'studyPlan' => $findUseCase->handle($this->viewingPlanId),
            'activeStudentCounts' => $countsUseCase->handle($this->viewingPlanId),
            'linkedCourseInfo' => $this->linkedCourseInfo(),
            'newCourseOptionsByLevel' => collect($this->structureLevels)
                ->mapWithKeys(fn (array $level) => [$level['key'] => $this->searchActiveCourses($this->newCourseSearchByLevel[$level['key']] ?? '')])
                ->all(),
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
     * Display info (code/name) for every course already linked into the
     * structure being edited — a level's course, or a prerequisite's
     * required/dependent course — keyed by course id. A targeted `whereIn`
     * over just those ids, never the whole catalog: unlike the "pick a new
     * course" pickers (searchActiveCourses(), narrowed to what's typed),
     * these ids are already fixed by the saved structure and can't be
     * limited to a search term the user never entered.
     *
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function linkedCourseInfo(): array
    {
        $courseIds = collect($this->structureLevels)
            ->flatMap(fn (array $level) => collect($level['courses'])->pluck('course_id'))
            ->merge(collect($this->structurePrerequisites)->pluck('required_course_id'))
            ->merge(collect($this->structurePrerequisites)->pluck('dependent_course_id'))
            ->unique()
            ->values();

        if ($courseIds->isEmpty()) {
            return [];
        }

        return CourseModel::query()->whereIn('id', $courseIds)->get(['id', 'code', 'name'])
            ->keyBy('id')
            ->map(fn (CourseModel $course): array => ['id' => $course->id, 'code' => $course->code, 'name' => $course->name])
            ->all();
    }
}
