<?php

declare(strict_types=1);

use App\Models\Permission;
use Livewire\Livewire;
use Src\IdentityAccess\Permission\Presentation\Livewire\PermissionComponent;

it('blocks a module or action containing characters outside the allowed identifier pattern', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.create']);

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->set('form.module', 'Study-Plans!')
        ->set('form.action', 'Export PDF')
        ->call('save')
        ->assertHasErrors(['form.module', 'form.action']);

    expect(Permission::query()->where('module', 'Study-Plans!')->exists())->toBeFalse();
});

it('allows a module and action following the snake_case identifier convention', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.create']);

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->set('form.module', 'study_plans_test')
        ->set('form.action', 'export_pdf')
        ->call('save')
        ->assertOk()
        ->assertHasNoErrors();

    expect(Permission::query()->where('module', 'study_plans_test')->where('action', 'export_pdf')->exists())->toBeTrue();
});
