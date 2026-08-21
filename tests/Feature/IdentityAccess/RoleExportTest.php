<?php

declare(strict_types=1);

use App\Models\Role;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;

/**
 * The export ports are swapped for spies here (see userWithPermissions(),
 * fakePdfExporter() and fakeExcelExporter() in tests/Pest.php), so the
 * component's behaviour is verified without Chromium or a real spreadsheet
 * writer. Two tests at the end cover the Blade template's own render.
 */
it('blocks a PDF export when the user lacks the export_pdf permission', function (): void {
    $user = userWithPermissions(['roles.view']);

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('blocks an Excel export when the user lacks the export_excel permission', function (): void {
    $user = userWithPermissions(['roles.view']);

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('having export_pdf does not grant the Excel export', function (): void {
    Storage::fake('local');
    $user = userWithPermissions(['roles.view', 'roles.export_pdf']);
    fakePdfExporter();

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportPdf')
        ->assertOk()
        ->call('exportExcel')
        ->assertForbidden();
});

it('exports every role to Excel with the on-screen column labels', function (): void {
    $user = userWithPermissions(['roles.view', 'roles.export_excel']);
    Role::factory()->create(['name' => 'Coordinator']);
    Role::factory()->create(['name' => 'Registrar']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportExcel')
        ->assertOk();

    // Derived rather than hardcoded: the filename follows the localized report
    // title, and only coincidentally reads the same under es and en here.
    expect($spy->filename)->toBe(Str::slug(__('Roles')).'.xlsx');
    expect(array_keys($spy->rows[0]))->toBe([__('Name'), __('Permissions'), __('Type')]);
    expect(array_column($spy->rows, __('Name')))->toContain('Coordinator', 'Registrar');
});

it('renders the protected flag as a label instead of a raw boolean', function (): void {
    $user = userWithPermissions(['roles.view', 'roles.export_excel']);
    Role::factory()->create(['name' => 'Custom role']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportExcel')
        ->assertOk();

    $types = array_unique(array_column($spy->rows, __('Type')));

    expect($types)->each->toBeIn([__('System'), __('Custom')]);
});

it('narrows the export to the search term the user has applied on screen', function (): void {
    $user = userWithPermissions(['roles.view', 'roles.export_excel']);
    Role::factory()->create(['name' => 'Coordinator']);
    Role::factory()->create(['name' => 'Registrar']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportExcel', search: 'Regist')
        ->assertOk();

    $names = array_column($spy->rows, __('Name'));

    expect($names)->toContain('Registrar');
    expect($names)->not->toContain('Coordinator');
});

it('exports the whole catalog when no search term is applied', function (): void {
    $user = userWithPermissions(['roles.view', 'roles.export_excel']);
    Role::factory()->create(['name' => 'Coordinator']);
    Role::factory()->create(['name' => 'Registrar']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportExcel')
        ->assertOk();

    expect(array_column($spy->rows, __('Name')))->toContain('Coordinator', 'Registrar');
});

it('queues a PDF export that renders letter-sized HTML carrying the report title and rows, ready to download', function (): void {
    Storage::fake('local');
    $user = userWithPermissions(['roles.view', 'roles.export_pdf']);
    Role::factory()->create(['name' => 'Coordinator']);
    $spy = fakePdfExporter();

    $component = Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->call('exportPdf')
        ->assertOk();

    // QUEUE_CONNECTION=sync in tests (phpunit.xml), so the job already ran
    // and Cache already holds 'ready' — checkPdfExportStatus() is what a
    // real poll tick would call to pull that into the component's own state.
    $component->call('checkPdfExportStatus');
    expect($component->get('pdfExportStatus'))->toBe('ready');
    expect($component->get('pdfExportFilename'))->toBe(Str::slug(__('Roles')).'.pdf');
    // The template's own @page rule is built for US Letter, so the component
    // has to ask for that size rather than the port's a4 default.
    expect($spy->paperSize)->toBe('letter');
    expect($spy->html)->toContain(__('Report of :title', ['title' => __('Roles')]));
    expect($spy->html)->toContain('Coordinator');

    $component->call('downloadQueuedPdf')->assertOk();
});

it('renders the PDF template standalone, without the asset pipeline', function (): void {
    $html = view('exports.table-pdf', [
        'title' => __('Roles'),
        'headers' => [['key' => 'name', 'label' => __('Name')]],
        'rows' => [[__('Name') => 'Coordinator']],
    ])->render();

    expect($html)->toContain(__('Issue date'));
    expect($html)->toContain(__('National Technical University'));
    expect($html)->toContain('Coordinator');
    // Fonts are base64-embedded precisely so a render needs no network call.
    // Checked structurally rather than by hostname: the template's own CSS
    // comment mentions fonts.googleapis.com to explain why it is NOT fetched.
    expect($html)->not->toContain('<link');
    expect($html)->not->toContain('url(http');
    expect($html)->toContain('src: url(data:font/woff2;base64,');
});

it('shows the empty-state row when there is nothing to export', function (): void {
    $html = view('exports.table-pdf', [
        'title' => __('Roles'),
        'headers' => [['key' => 'name', 'label' => __('Name')]],
        'rows' => [],
    ])->render();

    expect($html)->toContain(__('No records found'));
});
