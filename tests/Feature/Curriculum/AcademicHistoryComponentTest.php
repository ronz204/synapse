<?php

declare(strict_types=1);

use App\Enums\AcademicRecordStatus;
use App\Models\Course;
use App\Models\Equivalency;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
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
        // The status is rendered from the enum's stored value, which is the
        // exact wording RC-02b requires the course to be marked with.
        ->assertSee(AcademicRecordStatus::AccreditedByEquivalency->value)
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
        ->assertSee(AcademicRecordStatus::Passed->value);

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
