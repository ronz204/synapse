<?php

declare(strict_types=1);

use App\Models\StudyPlan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Curriculum\StudyPlan\Presentation\Livewire\StudyPlanComponent;

/**
 * Mirrors tests/Feature/IdentityAccess/RoleExportTest.php: the export ports
 * are swapped for spies (fakePdfExporter()/fakeExcelExporter() in
 * tests/Pest.php), so this verifies StudyPlanComponent's export behaviour
 * without Chromium or a real spreadsheet writer.
 */
it('blocks a PDF export when the user lacks the study_plans.export_pdf permission', function (): void {
    $user = userWithPermissions(['study_plans.view']);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('blocks an Excel export when the user lacks the study_plans.export_excel permission', function (): void {
    $user = userWithPermissions(['study_plans.view']);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('having study_plans.export_pdf does not grant the Excel export', function (): void {
    Storage::fake('local');
    $user = userWithPermissions(['study_plans.view', 'study_plans.export_pdf']);
    fakePdfExporter();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportPdf')
        ->assertOk()
        ->call('exportExcel')
        ->assertForbidden();
});

it('exports every study plan to Excel with the on-screen column labels', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.export_excel']);
    StudyPlan::factory()->create(['name' => 'Plan 2020']);
    StudyPlan::factory()->create(['name' => 'Plan 2024']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Study Plans')).'.xlsx');
    expect(array_keys($spy->rows[0]))->toBe([__('Name'), __('Implementation year'), __('Classification'), __('Enrollment closing date')]);
    expect(array_column($spy->rows, __('Name')))->toContain('Plan 2020', 'Plan 2024');
});

it('renders a missing enrollment closing date as an em dash instead of blank', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.export_excel']);
    StudyPlan::factory()->create(['name' => 'Active plan']);
    StudyPlan::factory()->terminal()->create(['name' => 'Terminal plan']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportExcel')
        ->assertOk();

    $rowsByName = collect($spy->rows)->keyBy(fn (array $row) => $row[__('Name')]);

    expect($rowsByName['Active plan'][__('Enrollment closing date')])->toBe('—');
    expect($rowsByName['Terminal plan'][__('Enrollment closing date')])->not->toBe('—');
});

it('narrows the export to the search term the user has applied on screen', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.export_excel']);
    StudyPlan::factory()->create(['name' => 'Plan 2020']);
    StudyPlan::factory()->create(['name' => 'Plan 2024']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportExcel', search: '2024')
        ->assertOk();

    $names = array_column($spy->rows, __('Name'));

    expect($names)->toContain('Plan 2024');
    expect($names)->not->toContain('Plan 2020');
});

it('exports the whole catalog when no search term is applied', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.export_excel']);
    StudyPlan::factory()->create(['name' => 'Plan 2020']);
    StudyPlan::factory()->create(['name' => 'Plan 2024']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_column($spy->rows, __('Name')))->toContain('Plan 2020', 'Plan 2024');
});

it('queues a PDF export that renders letter-sized HTML carrying the report title and rows, ready to download', function (): void {
    Storage::fake('local');
    $user = userWithPermissions(['study_plans.view', 'study_plans.export_pdf']);
    StudyPlan::factory()->create(['name' => 'Plan 2020']);
    $spy = fakePdfExporter();

    $component = Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('exportPdf')
        ->assertOk();

    // QUEUE_CONNECTION=sync in tests (phpunit.xml), so the job already ran
    // and Cache already holds 'ready' — checkPdfExportStatus() is what a
    // real poll tick would call to pull that into the component's own state.
    $component->call('checkPdfExportStatus');
    expect($component->get('pdfExportStatus'))->toBe('ready');
    expect($component->get('pdfExportFilename'))->toBe(Str::slug(__('Study Plans')).'.pdf');
    expect($spy->paperSize)->toBe('letter');
    expect($spy->html)->toContain(__('Report of :title', ['title' => __('Study Plans')]));
    expect($spy->html)->toContain('Plan 2020');

    $component->call('downloadQueuedPdf')->assertOk();
});
