<?php

declare(strict_types=1);

use App\Enums\AcademicRecordStatus;
use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Models\Course;
use App\Models\Equivalency;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\Curriculum\Accreditation\Application\UseCases\GrantAccreditationsForPassedCourseUseCase;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

beforeEach(function (): void {
    Storage::fake('local');
});

function accreditationPdfUpload(string $name = 'resolution.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'application/pdf');
}

function registerEquivalencyViaComponent(Course $source, Course $target, EquivalencyDirection $direction, string $resolutionNumber): void
{
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', $direction->value)
        ->set('form.resolutionNumber', $resolutionNumber)
        ->set('form.document', accreditationPdfUpload())
        ->call('register')
        ->assertOk()
        ->assertHasNoErrors();
}

it('accredits every student already passed on the source course when an OldToNew equivalency is registered', function (): void {
    $source = Course::factory()->create();
    $target = Course::factory()->create();
    $student = Student::factory()->create();

    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $source->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    registerEquivalencyViaComponent($source, $target, EquivalencyDirection::OldToNew, 'R-1');

    $equivalency = Equivalency::query()->where('resolution_number', 'R-1')->firstOrFail();

    $accreditation = StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $target->id)
        ->where('status', AcademicRecordStatus::AccreditedByEquivalency)
        ->first();

    expect($accreditation)->not->toBeNull();
    expect($accreditation->equivalency_id)->toBe($equivalency->id);
});

it('never accredits the reverse direction — a student who passed the target course is untouched by an OldToNew equivalency', function (): void {
    $source = Course::factory()->create();
    $target = Course::factory()->create();
    $student = Student::factory()->create();

    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $target->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    registerEquivalencyViaComponent($source, $target, EquivalencyDirection::OldToNew, 'R-1');

    expect(StudentAcademicRecord::query()->where('student_id', $student->id)->where('status', AcademicRecordStatus::AccreditedByEquivalency)->exists())->toBeFalse();
});

it('accredits both directions for a Bidirectional equivalency', function (): void {
    $courseA = Course::factory()->create();
    $courseB = Course::factory()->create();
    $studentPassedA = Student::factory()->create();
    $studentPassedB = Student::factory()->create();

    StudentAcademicRecord::query()->create(['student_id' => $studentPassedA->id, 'course_id' => $courseA->id, 'status' => AcademicRecordStatus::Passed]);
    StudentAcademicRecord::query()->create(['student_id' => $studentPassedB->id, 'course_id' => $courseB->id, 'status' => AcademicRecordStatus::Passed]);

    registerEquivalencyViaComponent($courseA, $courseB, EquivalencyDirection::Bidirectional, 'R-1');

    expect(StudentAcademicRecord::query()->where('student_id', $studentPassedA->id)->where('course_id', $courseB->id)->where('status', AcademicRecordStatus::AccreditedByEquivalency)->exists())->toBeTrue();
    expect(StudentAcademicRecord::query()->where('student_id', $studentPassedB->id)->where('course_id', $courseA->id)->where('status', AcademicRecordStatus::AccreditedByEquivalency)->exists())->toBeTrue();
});

it('accredits a student who passes the qualifying course after the equivalency was already active, via any code path', function (): void {
    $source = Course::factory()->create();
    $target = Course::factory()->create();
    $student = Student::factory()->create();

    $equivalency = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-1',
    ]);

    // Plain Eloquent create — not any RC-02b use case — proving the Observer
    // reacts to any code path that transitions a record to Passed.
    StudentAcademicRecord::query()->create([
        'student_id' => $student->id,
        'course_id' => $source->id,
        'status' => AcademicRecordStatus::Passed,
    ]);

    $accreditation = StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $target->id)
        ->where('status', AcademicRecordStatus::AccreditedByEquivalency)
        ->first();

    expect($accreditation)->not->toBeNull();
    expect($accreditation->equivalency_id)->toBe($equivalency->id);
});

it('never creates a duplicate accreditation when the same student/course is reprocessed', function (): void {
    $source = Course::factory()->create();
    $target = Course::factory()->create();
    $student = Student::factory()->create();

    Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-1',
    ]);

    $useCase = app(GrantAccreditationsForPassedCourseUseCase::class);
    $useCase->handle($student->id, $source->id);
    $useCase->handle($student->id, $source->id);

    expect(StudentAcademicRecord::query()->where('student_id', $student->id)->where('course_id', $target->id)->where('status', AcademicRecordStatus::AccreditedByEquivalency)->count())->toBe(1);
});

it('does not grant a redundant accreditation to a student who already satisfies the target course another way', function (): void {
    $source = Course::factory()->create();
    $target = Course::factory()->create();
    $student = Student::factory()->create();

    StudentAcademicRecord::query()->create(['student_id' => $student->id, 'course_id' => $target->id, 'status' => AcademicRecordStatus::AccreditedByValidation]);

    Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-1',
    ]);

    app(GrantAccreditationsForPassedCourseUseCase::class)->handle($student->id, $source->id);

    expect(StudentAcademicRecord::query()->where('student_id', $student->id)->where('course_id', $target->id)->count())->toBe(1);
});

it('leaves an accreditation granted while its equivalency was active untouched once that equivalency is superseded, and grants no further ones', function (): void {
    $source = Course::factory()->create();
    $target = Course::factory()->create();
    $student = Student::factory()->create();

    StudentAcademicRecord::query()->create(['student_id' => $student->id, 'course_id' => $source->id, 'status' => AcademicRecordStatus::Passed]);

    registerEquivalencyViaComponent($source, $target, EquivalencyDirection::OldToNew, 'R-EXISTING');
    $existing = Equivalency::query()->where('resolution_number', 'R-EXISTING')->firstOrFail();

    $accreditation = StudentAcademicRecord::query()
        ->where('student_id', $student->id)
        ->where('course_id', $target->id)
        ->where('status', AcademicRecordStatus::AccreditedByEquivalency)
        ->firstOrFail();

    expect($accreditation->equivalency_id)->toBe($existing->id);

    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create', 'equivalencies.resolve_contradiction']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CANDIDATE')
        ->set('form.document', accreditationPdfUpload())
        ->call('register')
        ->assertSet('conflictingEquivalencyId', $existing->id)
        ->call('resolveContradiction', 'candidate')
        ->assertOk();

    $existing->refresh();
    expect($existing->status)->toBe(EquivalencyStatus::Superseded);

    // The accreditation already granted while $existing was Active survives untouched.
    $accreditation->refresh();
    expect($accreditation->status)->toBe(AcademicRecordStatus::AccreditedByEquivalency);
    expect($accreditation->equivalency_id)->toBe($existing->id);

    // The newly-Active candidate covers the same pair, but the target course is
    // already satisfied — no second accreditation row is created for it.
    expect(StudentAcademicRecord::query()->where('student_id', $student->id)->where('course_id', $target->id)->count())->toBe(1);
});
