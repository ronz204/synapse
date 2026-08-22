<?php

declare(strict_types=1);

use App\Enums\AcademicRecordStatus;
use App\Enums\EquivalencyDirection;
use App\Models\Course;
use App\Models\Equivalency;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Curriculum\AcademicHistory\Application\UseCases\GetStudentAcademicHistoryUseCase;
use Src\Curriculum\AcademicHistory\Domain\Exceptions\StudentAcademicHistoryNotFoundException;
use Src\Curriculum\AcademicHistory\Presentation\Livewire\AcademicHistoryComponent;

/**
 * `equivalency_id` is deliberately outside StudentAcademicRecord's $fillable —
 * only the accreditation flow sets it — so a mass-assigned create() would
 * silently drop it and leave the record looking accredited by nobody.
 */
function accreditRecord(int $studentId, int $courseId, int $equivalencyId): StudentAcademicRecord
{
    $record = StudentAcademicRecord::query()->create([
        'student_id' => $studentId,
        'course_id' => $courseId,
        'status' => AcademicRecordStatus::AccreditedByEquivalency,
    ]);

    $record->equivalency_id = $equivalencyId;
    $record->save();

    return $record;
}

it('blocks mounting the component for a user without academic_records.view', function (): void {
    $user = userWithPermissions([]);

    Livewire::actingAs($user)->test(AcademicHistoryComponent::class)->assertForbidden();
});

it('lists students with their national id and name', function (): void {
    $user = userWithPermissions(['academic_records.view']);
    Student::factory()->create([
        'national_id' => '1-1111-1111',
        'first_name' => 'Ana',
        'first_last_name' => 'Rojas',
        'second_last_name' => 'Mora',
    ]);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->assertOk()
        ->assertSee('1-1111-1111')
        ->assertSee('Ana Rojas Mora');
});

it('narrows the listing with the search box', function (): void {
    $user = userWithPermissions(['academic_records.view']);
    Student::factory()->create(['first_last_name' => 'Rojas', 'national_id' => '1-1111-1111']);
    Student::factory()->create(['first_last_name' => 'Zamora', 'national_id' => '2-2222-2222']);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->set('search', 'Zamora')
        ->assertSee('2-2222-2222')
        ->assertDontSee('1-1111-1111');
});

it('shows a course accredited by equivalency together with its resolution number', function (): void {
    $user = userWithPermissions(['academic_records.view']);
    $student = Student::factory()->create();
    $course = Course::factory()->create(['code' => 'HIST-101', 'name' => 'Accredited course']);
    $equivalency = Equivalency::factory()->create(['resolution_number' => 'R-HIST-001']);

    accreditRecord($student->id, $course->id, $equivalency->id);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->assertSee('HIST-101')
        ->assertSee('Accredited course')
        // The status is rendered as a human-readable, localized label — not
        // the raw enum value — matching the wording RC-02b requires the
        // course to be marked with.
        ->assertSee(__(Str::headline(AcademicRecordStatus::AccreditedByEquivalency->name)))
        ->assertSee('R-HIST-001');
});

it('leaves the resolution column empty for a course the student actually passed', function (): void {
    $user = userWithPermissions(['academic_records.view']);
    $student = Student::factory()->create();
    $course = Course::factory()->create(['code' => 'HIST-202']);

    StudentAcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->assertSee('HIST-202')
        ->assertSee(__(Str::headline(AcademicRecordStatus::Passed->name)));

    // Nothing to cite: the entry carries no resolution for the view to print.
    $history = app(GetStudentAcademicHistoryUseCase::class)->handle($student->id);

    expect($history->entries()[0]->resolutionNumber)->toBeNull();
    expect($history->accreditedByEquivalency())->toBeEmpty();
});

it('returns to the student listing from a history', function (): void {
    $user = userWithPermissions(['academic_records.view']);
    $student = Student::factory()->create(['national_id' => '3-3333-3333']);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->assertSet('viewingStudentId', $student->id)
        ->call('backToStudents')
        ->assertSet('viewingStudentId', null)
        ->assertSee('3-3333-3333');
});

it('refuses to open a history for a student that does not exist', function (): void {
    $user = userWithPermissions(['academic_records.view']);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', 987654);
})->throws(StudentAcademicHistoryNotFoundException::class);

it('reads a history straight from the use case, without going through the component', function (): void {
    $student = Student::factory()->create([
        'first_name' => 'Luis',
        'first_last_name' => 'Vega',
        'second_last_name' => 'Solano',
    ]);
    $course = Course::factory()->create(['code' => 'HIST-303']);
    $equivalency = Equivalency::factory()->create(['resolution_number' => 'R-HIST-303']);

    accreditRecord($student->id, $course->id, $equivalency->id);

    $history = app(GetStudentAcademicHistoryUseCase::class)->handle($student->id);

    expect($history->student()->fullName)->toBe('Luis Vega Solano');
    expect($history->entries())->toHaveCount(1);
    expect($history->entries()[0]->courseCode)->toBe('HIST-303');
    expect($history->entries()[0]->resolutionNumber)->toBe('R-HIST-303');
    expect($history->accreditedByEquivalency())->toHaveCount(1);
});

it('records a passed course from the history and applies its valid equivalency', function (): void {
    $user = userWithPermissions(['academic_records.view', 'academic_records.create']);
    $student = Student::factory()->create();
    $source = Course::factory()->create(['code' => 'INPUT-OLD', 'name' => 'Input source']);
    $target = Course::factory()->create(['code' => 'INPUT-NEW', 'name' => 'Input target']);

    Equivalency::factory()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'direction' => EquivalencyDirection::OldToNew,
        'resolution_number' => 'INPUT-RES-001',
    ]);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->call('openPassedCourseModal')
        ->call('selectCourse', $source->id, $source->code, $source->name)
        ->call('recordPassedCourse')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertSee('INPUT-OLD')
        ->assertSee('INPUT-NEW')
        ->assertSee('INPUT-RES-001');

    expect(StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $source->id)
        ->where('status', AcademicRecordStatus::Passed)
        ->exists())->toBeTrue();

    expect(StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $target->id)
        ->where('status', AcademicRecordStatus::AccreditedByEquivalency)
        ->exists())->toBeTrue();
});

it('requires create permission to open the passed-course input', function (): void {
    $user = userWithPermissions(['academic_records.view']);
    $student = Student::factory()->create();

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->call('openPassedCourseModal')
        ->assertForbidden();
});

it('does not accredit in reverse when the passed course is entered from the history', function (): void {
    $user = userWithPermissions(['academic_records.view', 'academic_records.create']);
    $student = Student::factory()->create();
    $source = Course::factory()->create(['code' => 'INPUT-REVERSE-OLD']);
    $target = Course::factory()->create(['code' => 'INPUT-REVERSE-NEW']);

    Equivalency::factory()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'direction' => EquivalencyDirection::OldToNew,
    ]);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->call('openPassedCourseModal')
        ->call('selectCourse', $target->id, $target->code, $target->name)
        ->call('recordPassedCourse')
        ->assertHasNoErrors()
        ->assertSee('INPUT-REVERSE-NEW')
        ->assertDontSee('INPUT-REVERSE-OLD');

    expect(StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $source->id)
        ->exists())->toBeFalse();
});

it('does not duplicate a course the student already passed', function (): void {
    $user = userWithPermissions(['academic_records.view', 'academic_records.create']);
    $student = Student::factory()->create();
    $course = Course::factory()->create(['code' => 'INPUT-DUP']);

    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    Livewire::actingAs($user)
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->call('openPassedCourseModal')
        ->call('selectCourse', $course->id, $course->code, $course->name)
        ->call('recordPassedCourse')
        ->assertHasErrors(['form.courseId'])
        ->assertSet('showModal', true);

    expect(StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->count())->toBe(1);
});
