<?php

declare(strict_types=1);

use App\Models\Modality;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityComponent;

/**
 * Mirrors tests/Feature/IdentityAccess/RoleExportTest.php: the export ports
 * are swapped for spies (fakePdfExporter()/fakeExcelExporter() in
 * tests/Pest.php), so this verifies ModalityComponent's export behaviour
 * without Chromium or a real spreadsheet writer.
 */
it('blocks a PDF export when the user lacks the modalities.export_pdf permission', function (): void {
    $user = userWithPermissions(['modalities.view']);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('blocks an Excel export when the user lacks the modalities.export_excel permission', function (): void {
    $user = userWithPermissions(['modalities.view']);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('having modalities.export_pdf does not grant the Excel export', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.export_pdf']);
    fakePdfExporter();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportPdf')
        ->assertOk()
        ->call('exportExcel')
        ->assertForbidden();
});

it('exports every modality to Excel with the on-screen column labels', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.export_excel']);
    Modality::factory()->create(['name' => 'Presencial']);
    Modality::factory()->create(['name' => 'Virtual']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Modalities')).'.xlsx');
    expect(array_keys($spy->rows[0]))->toBe([__('Name'), __('Requires resolution')]);
    expect(array_column($spy->rows, __('Name')))->toContain('Presencial', 'Virtual');
});

it('renders the requires-resolution flag as a label instead of a raw boolean', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.export_excel']);
    Modality::factory()->create(['name' => 'Presencial', 'requires_resolution' => false]);
    Modality::factory()->requiresResolution()->create(['name' => 'Semipresencial']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_unique(array_column($spy->rows, __('Requires resolution'))))->each->toBeIn([__('Yes'), __('No')]);
});

it('narrows the export to the search term the user has applied on screen', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.export_excel']);
    Modality::factory()->create(['name' => 'Presencial']);
    Modality::factory()->create(['name' => 'Virtual']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportExcel', search: 'Virtual')
        ->assertOk();

    $names = array_column($spy->rows, __('Name'));

    expect($names)->toContain('Virtual');
    expect($names)->not->toContain('Presencial');
});

it('exports the whole catalog when no search term is applied', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.export_excel']);
    Modality::factory()->create(['name' => 'Presencial']);
    Modality::factory()->create(['name' => 'Virtual']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_column($spy->rows, __('Name')))->toContain('Presencial', 'Virtual');
});

it('hands the PDF port letter-sized HTML carrying the report title and rows', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.export_pdf']);
    Modality::factory()->create(['name' => 'Presencial']);
    $spy = fakePdfExporter();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('exportPdf')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Modalities')).'.pdf');
    expect($spy->paperSize)->toBe('letter');
    expect($spy->html)->toContain(__('Report of :title', ['title' => __('Modalities')]));
    expect($spy->html)->toContain('Presencial');
});
