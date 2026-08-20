<?php

declare(strict_types=1);

/**
 * RC-02b — Acreditación Informativa por Equiparación.
 *
 * One test per acceptance criterion listed in `.claude/docs/approach.md`. The
 * second criterion (never accrediting against the registered direction) is
 * the one the requirement singles out as "test the reverse case explicitly".
 */

use App\Enums\AcademicRecordStatus;
use App\Enums\EquivalencyDirection;
use App\Models\Course;
use App\Models\Equivalency;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\Curriculum\AcademicHistory\Presentation\Livewire\AcademicHistoryComponent;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

beforeEach(function (): void {
    Storage::fake('local');
});

function rc02bRegisterEquivalency(Course $source, Course $target, EquivalencyDirection $direction, string $resolutionNumber): Equivalency
{
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', $direction->value)
        ->set('form.resolutionNumber', $resolutionNumber)
        ->set('form.document', UploadedFile::fake()->create('resolucion.pdf', 100, 'application/pdf'))
        ->call('register')
        ->assertHasNoErrors();

    return Equivalency::query()->where('resolution_number', $resolutionNumber)->firstOrFail();
}

it('RC-02b AC1 — saving an "old → new" equivalency accredits the new course for every student who passed the old one, citing the resolution', function (): void {
    $oldCourse = Course::factory()->create(['code' => 'RC02B-OLD']);
    $newCourse = Course::factory()->create(['code' => 'RC02B-NEW']);
    $student = Student::factory()->create();
    $unrelatedStudent = Student::factory()->create();

    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $oldCourse->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    $equivalency = rc02bRegisterEquivalency($oldCourse, $newCourse, EquivalencyDirection::OldToNew, 'RC02B-001');

    $accreditation = StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $newCourse->id)
        ->first();

    expect($accreditation)->not->toBeNull();

    // "Acreditado por equiparación — Resolución [número]": the status carries
    // the label, the equivalency link carries the resolution number.
    expect($accreditation->status)->toBe(AcademicRecordStatus::AccreditedByEquivalency);
    expect($accreditation->status->value)->toBe('accredited_by_equivalency');
    expect($accreditation->equivalency_id)->toBe($equivalency->id);
    expect(Equivalency::query()->whereKey($accreditation->equivalency_id)->firstOrFail()->resolution_number)
        ->toBe('RC02B-001');

    // Only students who actually passed the source course are touched.
    expect(StudentAcademicRecord::query()->where('student_id', $unrelatedStudent->id)->exists())->toBeFalse();
});

it('RC-02b AC2 — no student is accredited in the reverse direction to the one the resolution approved', function (): void {
    $oldCourse = Course::factory()->create(['code' => 'RC02B-OLD-REV']);
    $newCourse = Course::factory()->create(['code' => 'RC02B-NEW-REV']);

    // This student passed the *target* course. An "old → new" resolution says
    // nothing about them, so they must gain nothing from it.
    $student = Student::factory()->create();

    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $newCourse->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    rc02bRegisterEquivalency($oldCourse, $newCourse, EquivalencyDirection::OldToNew, 'RC02B-002');

    expect(StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $oldCourse->id)
        ->exists())->toBeFalse();

    expect(StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('status', AcademicRecordStatus::AccreditedByEquivalency)
        ->exists())->toBeFalse();
});

it('RC-02b — a "new → old" resolution accredits only in that registered direction', function (): void {
    // The mirror of AC2: proves the direction is genuinely read off the
    // record rather than the source/target columns being assumed one way.
    $oldCourse = Course::factory()->create(['code' => 'RC02B-OLD-N2O']);
    $newCourse = Course::factory()->create(['code' => 'RC02B-NEW-N2O']);

    $passedNew = Student::factory()->create();
    $passedOld = Student::factory()->create();

    StudentAcademicRecord::query()->create([
        'student_id' => $passedNew->id,
        'course_id' => $newCourse->id,
        'status' => AcademicRecordStatus::Passed,
    ]);
    StudentAcademicRecord::query()->create([
        'student_id' => $passedOld->id,
        'course_id' => $oldCourse->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    rc02bRegisterEquivalency($oldCourse, $newCourse, EquivalencyDirection::NewToOld, 'RC02B-003');

    // Passed the new course → accredited for the old one.
    expect(StudentAcademicRecord::query()
        ->where('student_id', $passedNew->id)
        ->where('course_id', $oldCourse->id)
        ->where('status', AcademicRecordStatus::AccreditedByEquivalency)
        ->exists())->toBeTrue();

    // Passed the old course → nothing, the resolution does not run that way.
    expect(StudentAcademicRecord::query()
        ->where('student_id', $passedOld->id)
        ->where('course_id', $newCourse->id)
        ->exists())->toBeFalse();
});

it('RC-02b Entradas/Salidas — the student list and the accredited course with its resolution are readable in the application', function (): void {
    // The two criteria above assert the state of the history. This asserts
    // the requirement's stated input and output: that the list of test
    // students can be consulted, and that the accredited course is legible
    // *together with* the resolution number — neither of which the domain
    // assertions cover, since a correct record nobody can reach still leaves
    // the requirement's Salidas unmet.
    $oldCourse = Course::factory()->create(['code' => 'RC02B-VIEW-OLD']);
    $newCourse = Course::factory()->create(['code' => 'RC02B-VIEW-NEW', 'name' => 'Course reached by equivalency']);
    $student = Student::factory()->create(['national_id' => '9-9999-9999']);

    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $oldCourse->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    rc02bRegisterEquivalency($oldCourse, $newCourse, EquivalencyDirection::OldToNew, 'RC02B-VIEW-001');

    $reader = userWithPermissions(['academic_records.view']);

    Livewire::actingAs($reader)
        ->test(AcademicHistoryComponent::class)
        // Entrada: the list of test students is consultable.
        ->assertSee('9-9999-9999')
        ->call('viewHistory', $student->id)
        // Salida: the target course, marked accredited, next to its resolution.
        ->assertSee('RC02B-VIEW-NEW')
        ->assertSee('Course reached by equivalency')
        ->assertSee(AcademicRecordStatus::AccreditedByEquivalency->value)
        ->assertSee('RC02B-VIEW-001');
});

it('RC-02b Salidas — a student the resolution does not reach shows no accreditation in their history', function (): void {
    $oldCourse = Course::factory()->create(['code' => 'RC02B-BLANK-OLD']);
    $newCourse = Course::factory()->create(['code' => 'RC02B-BLANK-NEW']);
    $student = Student::factory()->create();

    // Passed the *new* course, while the resolution runs old → new: nothing
    // should be accredited for this student.
    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $newCourse->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    rc02bRegisterEquivalency($oldCourse, $newCourse, EquivalencyDirection::OldToNew, 'RC02B-BLANK-001');

    Livewire::actingAs(userWithPermissions(['academic_records.view']))
        ->test(AcademicHistoryComponent::class)
        ->call('viewHistory', $student->id)
        ->assertSee('RC02B-BLANK-NEW')
        ->assertSee(AcademicRecordStatus::Passed->value)
        ->assertDontSee(AcademicRecordStatus::AccreditedByEquivalency->value)
        ->assertDontSee('RC02B-BLANK-001');
});
