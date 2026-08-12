<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\IdentityAccess\Permission\Presentation\Livewire\PermissionComponent;

/**
 * PermissionComponent's export path mirrors RoleComponent's, so these cover the
 * parts that could drift between the two — the permission names each gate
 * checks, the filename, and the column set — rather than re-testing the shared
 * InteractsWithExports plumbing. Shared helpers live in tests/Pest.php.
 */
it('blocks a PDF export when the user lacks the export_pdf permission', function (): void {
    $user = userWithPermissions(['permissions.view']);

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportPdf')
        ->assertForbidden();
});

it('blocks an Excel export when the user lacks the export_excel permission', function (): void {
    $user = userWithPermissions(['permissions.view']);

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('does not accept the roles export permission in place of its own', function (): void {
    $user = userWithPermissions(['permissions.view', 'roles.export_excel']);

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportExcel')
        ->assertForbidden();
});

it('exports permissions to Excel with the module, action and name columns', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.export_excel']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportExcel')
        ->assertOk();

    // The filename is localized along with the report title, so it is derived
    // the same way here rather than hardcoded ('permisos.xlsx' under es).
    expect($spy->filename)->toBe(Str::slug(__('Permissions')).'.xlsx');
    expect(array_keys($spy->rows[0]))->toBe([__('Module'), __('Action'), __('Name')]);
    // userWithPermissions() created these two rows, so they must both appear.
    expect(array_column($spy->rows, __('Name')))
        ->toContain('permissions.view', 'permissions.export_excel');
});

it('narrows the permissions export to the search term applied on screen', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.export_excel', 'roles.view']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportExcel', search: 'roles')
        ->assertOk();

    $names = array_column($spy->rows, __('Name'));

    expect($names)->toContain('roles.view');
    expect($names)->not->toContain('permissions.view');
});

it('asks the PDF port for a letter-sized permissions report', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.export_pdf']);
    $spy = fakePdfExporter();

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportPdf')
        ->assertOk();

    expect($spy->filename)->toBe(Str::slug(__('Permissions')).'.pdf');
    expect($spy->paperSize)->toBe('letter');
    expect($spy->html)->toContain(__('Report of :title', ['title' => __('Permissions')]));
});
