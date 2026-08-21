<?php

declare(strict_types=1);

use App\Models\Equivalency;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

/**
 * Mirrors tests/Feature/IdentityAccess/RoleExportTest.php: the export ports
 * are swapped for spies (fakePdfExporter()/fakeExcelExporter() in
 * tests/Pest.php), so this verifies EquivalencyComponent's export behaviour
 * without Chromium or a real spreadsheet writer.
 */
it('blocks a PDF export when the user lacks the equivalencies.export_pdf permission', function (): void {
    $user = userWithPermissions(['equivalencies.view']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('blocks an Excel export when the user lacks the equivalencies.export_excel permission', function (): void {
    $user = userWithPermissions(['equivalencies.view']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('having equivalencies.export_pdf does not grant the Excel export', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.export_pdf']);
    fakePdfExporter();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportPdf')
        ->assertOk()
        ->call('exportExcel')
        ->assertForbidden();
});

it('exports every equivalency to Excel with the on-screen column labels', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.export_excel']);
    Equivalency::factory()->create(['resolution_number' => 'R-1001']);
    Equivalency::factory()->create(['resolution_number' => 'R-1002']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Equivalencies')).'.xlsx');
    expect(array_keys($spy->rows[0]))->toBe([__('Source course'), __('Target course'), __('Direction'), __('Resolution number'), __('Status')]);
    expect(array_column($spy->rows, __('Resolution number')))->toContain('R-1001', 'R-1002');
});

it('translates the direction and status enum values instead of raw case names', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.export_excel']);
    Equivalency::factory()->bidirectional()->create();
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect($spy->rows[0][__('Direction')])->not->toBe('Bidirectional');
    expect($spy->rows[0][__('Status')])->not->toBe('Active');
});

it('narrows the export to the search term the user has applied on screen', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.export_excel']);
    Equivalency::factory()->create(['resolution_number' => 'R-1001']);
    Equivalency::factory()->create(['resolution_number' => 'R-1002']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportExcel', search: 'R-1001')
        ->assertOk();

    $numbers = array_column($spy->rows, __('Resolution number'));

    expect($numbers)->toContain('R-1001');
    expect($numbers)->not->toContain('R-1002');
});

it('exports the whole catalog when no search term is applied', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.export_excel']);
    Equivalency::factory()->create(['resolution_number' => 'R-1001']);
    Equivalency::factory()->create(['resolution_number' => 'R-1002']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_column($spy->rows, __('Resolution number')))->toContain('R-1001', 'R-1002');
});

it('hands the PDF port letter-sized HTML carrying the report title and rows', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.export_pdf']);
    Equivalency::factory()->create(['resolution_number' => 'R-1001']);
    $spy = fakePdfExporter();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('exportPdf')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Equivalencies')).'.pdf');
    expect($spy->paperSize)->toBe('letter');
    expect($spy->html)->toContain(__('Report of :title', ['title' => __('Equivalencies')]));
    expect($spy->html)->toContain('R-1001');
});
