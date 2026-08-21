<?php

declare(strict_types=1);

use App\Models\Course;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;

/**
 * Mirrors tests/Feature/IdentityAccess/RoleExportTest.php: the export ports
 * are swapped for spies (fakePdfExporter()/fakeExcelExporter() in
 * tests/Pest.php), so this verifies CourseComponent's export behaviour
 * without Chromium or a real spreadsheet writer.
 */
it('blocks a PDF export when the user lacks the courses.export_pdf permission', function (): void {
    $user = userWithPermissions(['courses.view']);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('blocks an Excel export when the user lacks the courses.export_excel permission', function (): void {
    $user = userWithPermissions(['courses.view']);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('having courses.export_pdf does not grant the Excel export', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.export_pdf']);
    fakePdfExporter();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportPdf')
        ->assertOk()
        ->call('exportExcel')
        ->assertForbidden();
});

it('exports every course to Excel with the on-screen column labels', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.export_excel']);
    Course::factory()->create(['code' => 'DEMO-201', 'name' => 'Compilers']);
    Course::factory()->create(['code' => 'DEMO-202', 'name' => 'Operating Systems']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Courses')).'.xlsx');
    expect(array_keys($spy->rows[0]))->toBe([__('Code'), __('Name'), __('Service course'), __('Modality'), __('Status')]);
    expect(array_column($spy->rows, __('Code')))->toContain('DEMO-201', 'DEMO-202');
});

it('renders the service-course and status flags as labels instead of raw booleans', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.export_excel']);
    Course::factory()->create(['is_service' => false, 'active' => true]);
    Course::factory()->service()->create(['active' => false]);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_unique(array_column($spy->rows, __('Service course'))))->each->toBeIn([__('Yes'), __('No')]);
    expect(array_unique(array_column($spy->rows, __('Status'))))->each->toBeIn([__('Active'), __('Inactive')]);
});

it('narrows the export to the search term the user has applied on screen', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.export_excel']);
    Course::factory()->create(['code' => 'DEMO-201', 'name' => 'Compilers']);
    Course::factory()->create(['code' => 'DEMO-202', 'name' => 'Operating Systems']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportExcel', search: 'Compilers')
        ->assertOk();

    $codes = array_column($spy->rows, __('Code'));

    expect($codes)->toContain('DEMO-201');
    expect($codes)->not->toContain('DEMO-202');
});

it('exports the whole catalog when no search term is applied', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.export_excel']);
    Course::factory()->create(['code' => 'DEMO-201']);
    Course::factory()->create(['code' => 'DEMO-202']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_column($spy->rows, __('Code')))->toContain('DEMO-201', 'DEMO-202');
});

it('hands the PDF port letter-sized HTML carrying the report title and rows', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.export_pdf']);
    Course::factory()->create(['code' => 'DEMO-201', 'name' => 'Compilers']);
    $spy = fakePdfExporter();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('exportPdf')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Courses')).'.pdf');
    expect($spy->paperSize)->toBe('letter');
    expect($spy->html)->toContain(__('Report of :title', ['title' => __('Courses')]));
    expect($spy->html)->toContain('Compilers');
});
