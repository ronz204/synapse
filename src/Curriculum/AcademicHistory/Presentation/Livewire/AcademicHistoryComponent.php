<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithCourseSearch;
use App\Livewire\Concerns\InteractsWithDataTable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Src\Curriculum\AcademicHistory\Application\UseCases\GetStudentAcademicHistoryUseCase;
use Src\Curriculum\AcademicHistory\Application\UseCases\ListStudentsUseCase;
use Src\Curriculum\AcademicHistory\Application\UseCases\RecordPassedCourseUseCase;
use Src\Curriculum\AcademicHistory\Domain\Entities\StudentAcademicHistory;
use Src\Curriculum\AcademicHistory\Domain\Exceptions\CourseAlreadySatisfiedException;
use Src\Curriculum\AcademicHistory\Presentation\Livewire\Forms\PassedCourseForm;

/**
 * Two screens behind one component, the same shape StudyPlanComponent uses:
 * the student listing, and one student's history once a row is opened.
 *
 * Server mode, unlike every other CRUD screen in the project. Those load
 * their whole collection into Alpine and filter in the browser, which is
 * fine for hundreds of courses or plans but not for a student body — here
 * search, sort and pagination stay on the server.
 */
#[Layout('layouts.dashboard', ['title' => 'Academic history', 'subtitle' => 'Simplified internal history per student'])]
class AcademicHistoryComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithCourseSearch;
    use InteractsWithDataTable;

    protected string $tableMode = 'server';

    /**
     * Non-null switches the view from the student listing to that student's
     * history.
     */
    public ?int $viewingStudentId = null;

    public bool $showModal = false;

    public string $courseSearch = '';

    public PassedCourseForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', StudentAcademicHistory::class);
        $this->sortKey = 'first_last_name';
        $this->sortDir = 'asc';
    }

    /**
     * The history is resolved here, before the id is stored, so a row
     * pointing at a student that no longer exists fails on the click that
     * caused it rather than on the next render.
     */
    public function viewHistory(int $studentId, GetStudentAcademicHistoryUseCase $useCase): void
    {
        $this->authorize('viewAny', StudentAcademicHistory::class);

        $useCase->handle($studentId);

        $this->viewingStudentId = $studentId;
    }

    public function backToStudents(): void
    {
        $this->closeModal();
        $this->viewingStudentId = null;
    }

    public function openPassedCourseModal(): void
    {
        $this->authorize('create', StudentAcademicHistory::class);
        abort_if($this->viewingStudentId === null, 404);

        $this->form->reset();
        $this->courseSearch = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function selectCourse(int $courseId, string $code, string $name): void
    {
        $this->form->courseId = $courseId;
        $this->courseSearch = $code.' - '.$name;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function recordPassedCourse(RecordPassedCourseUseCase $useCase): void
    {
        $this->authorize('create', StudentAcademicHistory::class);
        abort_if($this->viewingStudentId === null, 404);
        $this->form->validate();

        try {
            $useCase->handle($this->viewingStudentId, (int) $this->form->courseId);
        } catch (CourseAlreadySatisfiedException) {
            $this->addError('form.courseId', __('This student already has a passing outcome for the selected course.'));

            return;
        }

        $this->showModal = false;
        Flux::toast(variant: 'success', text: __('Passed course recorded.'));
    }

    public function render(ListStudentsUseCase $useCase, GetStudentAcademicHistoryUseCase $historyUseCase): View
    {
        if ($this->viewingStudentId !== null) {
            return view('curriculum.academic-history.livewire.academic-history-detail', [
                'history' => $historyUseCase->handle($this->viewingStudentId),
                'courseOptions' => $this->searchActiveCourses($this->courseSearch),
            ]);
        }

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

        return view('curriculum.academic-history.livewire.academic-history-component', [
            'tableMode' => 'server',
            'students' => $paginator,
        ]);
    }
}
