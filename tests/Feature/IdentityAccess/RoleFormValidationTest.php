<?php

declare(strict_types=1);

use App\Models\Role;
use Livewire\Livewire;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;

it('blocks a role name containing characters outside the allowed name pattern', function (): void {
    $user = userWithPermissions(['roles.view', 'roles.create']);

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->set('form.name', 'Coordinador <script>#!')
        ->call('save')
        ->assertHasErrors(['form.name']);

    expect(Role::query()->where('name', 'Coordinador <script>#!')->exists())->toBeFalse();
});

it('allows a role name with accented letters and punctuation this project actually uses', function (): void {
    $user = userWithPermissions(['roles.view', 'roles.create']);

    Livewire::actingAs($user)
        ->test(RoleComponent::class)
        ->set('form.name', 'Coordinador Académico (Sede San Carlos)')
        ->call('save')
        ->assertOk()
        ->assertHasNoErrors();

    expect(Role::query()->where('name', 'Coordinador Académico (Sede San Carlos)')->exists())->toBeTrue();
});
