<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Modality;
use App\Models\ModalityResolution;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityAssignmentComponent;

/**
 * Mirrors tests/Feature/IdentityAccess/RoleExportTest.php: the export ports
 * are swapped for spies (fakePdfExporter()/fakeExcelExporter() in
 * tests/Pest.php), so this verifies ModalityAssignmentComponent's export
 * behaviour without Chromium or a real spreadsheet writer. Authorization
 * here gates against ModalityResolution::class, using the
 * modality_resolutions.* permission prefix — distinct from ModalityPolicy's
 * modalities.* prefix used for catalog maintenance.
 */
it('blocks a PDF export when the user lacks the modality_resolutions.export_pdf permission', function (): void {
    $user = userWithPermissions(['modality_resolutions.view']);

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('blocks an Excel export when the user lacks the modality_resolutions.export_excel permission', function (): void {
    $user = userWithPermissions(['modality_resolutions.view']);

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('having modality_resolutions.export_pdf does not grant the Excel export', function (): void {
    Storage::fake('local');
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.export_pdf']);
    fakePdfExporter();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportPdf')
        ->assertOk()
        ->call('exportExcel')
        ->assertForbidden();
});

it('exports every course-modality assignment to Excel with the on-screen column labels', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.export_excel']);
    $modality = Modality::factory()->requiresResolution()->create(['name' => 'Semipresencial']);
    $course = Course::factory()->create(['code' => 'DEMO-301', 'name' => 'Networks', 'modality_id' => $modality->id]);
    ModalityResolution::factory()->create([
        'course_id' => $course->id,
        'modality_id' => $modality->id,
        'resolution_number' => 'R-2001',
        'valid_from' => now()->subMonth(),
        'valid_to' => null,
    ]);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Modality Assignments')).'.xlsx');
    expect(array_keys($spy->rows[0]))->toBe([
        __('Code'), __('Name'), __('Modality'), __('Resolution number'), __('Valid from'), __('Valid to'), __('Currently valid'),
    ]);
    expect(array_column($spy->rows, __('Code')))->toContain('DEMO-301');
});

it('renders a currently-valid resolution as Yes and a missing one as No', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.export_excel']);
    $modality = Modality::factory()->requiresResolution()->create();
    $courseWithResolution = Course::factory()->create(['code' => 'DEMO-301', 'modality_id' => $modality->id]);
    ModalityResolution::factory()->create([
        'course_id' => $courseWithResolution->id,
        'modality_id' => $modality->id,
        'valid_from' => now()->subMonth(),
        'valid_to' => null,
    ]);
    // Default modality doesn't require a resolution, so this course never gets one.
    Course::factory()->create(['code' => 'DEMO-302']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportExcel')
        ->assertOk();

    $rowsByCode = collect($spy->rows)->keyBy(fn (array $row) => $row[__('Code')]);

    expect($rowsByCode['DEMO-301'][__('Currently valid')])->toBe(__('Yes'));
    expect($rowsByCode['DEMO-302'][__('Currently valid')])->toBe(__('No'));
});

it('narrows the export to the search term the user has applied on screen', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.export_excel']);
    Course::factory()->create(['code' => 'DEMO-301', 'name' => 'Networks']);
    Course::factory()->create(['code' => 'DEMO-302', 'name' => 'Databases']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportExcel', search: 'Networks')
        ->assertOk();

    $codes = array_column($spy->rows, __('Code'));

    expect($codes)->toContain('DEMO-301');
    expect($codes)->not->toContain('DEMO-302');
});

it('exports the whole catalog when no search term is applied', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.export_excel']);
    Course::factory()->create(['code' => 'DEMO-301']);
    Course::factory()->create(['code' => 'DEMO-302']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_column($spy->rows, __('Code')))->toContain('DEMO-301', 'DEMO-302');
});

it('queues a PDF export that renders letter-sized HTML carrying the report title and rows, ready to download', function (): void {
    Storage::fake('local');
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.export_pdf']);
    Course::factory()->create(['code' => 'DEMO-301', 'name' => 'Networks']);
    $spy = fakePdfExporter();

    $component = Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('exportPdf')
        ->assertOk();

    // QUEUE_CONNECTION=sync in tests (phpunit.xml), so the job already ran
    // and Cache already holds 'ready' — checkPdfExportStatus() is what a
    // real poll tick would call to pull that into the component's own state.
    $component->call('checkPdfExportStatus');
    expect($component->get('pdfExportStatus'))->toBe('ready');
    expect($component->get('pdfExportFilename'))->toBe(Str::slug(__('Modality Assignments')).'.pdf');
    expect($spy->paperSize)->toBe('letter');
    expect($spy->html)->toContain(__('Report of :title', ['title' => __('Modality Assignments')]));
    expect($spy->html)->toContain('DEMO-301');

    $component->call('downloadQueuedPdf')->assertOk();
});
