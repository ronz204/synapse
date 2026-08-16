<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Src\IdentityAccess\Permission\Domain\Entities\Permission as PermissionEntity;

/**
 * Superadmin's access ran through two independent mechanisms that silently
 * disagreed: DomainServiceProvider's Gate::before, which can() consults, and
 * the role -> permission pivot, which hasPermissionTo() reads directly. The
 * seeders left that pivot empty, so every Blade checking hasPermissionTo()
 * hid its controls for the one role meant to have unconditional access.
 *
 * These cover both halves: the pivot really gets filled, and the two checks
 * agree even for a permission the seeders never saw.
 */
it('grants Superadmin every seeded permission in the pivot', function (): void {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, PermissionRoleSeeder::class]);

    $superadmin = Role::query()
        ->where('name', User::SUPERADMIN_ROLE)
        ->firstOrFail();

    expect($superadmin->permissions()->count())
        ->toBe(Permission::query()->count())
        ->toBeGreaterThan(0);
});

it('keeps the seeded pivot honest regardless of seeder ordering', function (): void {
    // RoleSeeder used to sync the pivot itself, before any permission existed.
    // Running it first must still leave Superadmin fully granted.
    $this->seed(RoleSeeder::class);

    expect(Permission::query()->count())->toBe(0);

    $this->seed([PermissionSeeder::class, PermissionRoleSeeder::class]);

    $superadmin = Role::query()
        ->where('name', User::SUPERADMIN_ROLE)
        ->firstOrFail();

    expect($superadmin->permissions()->count())->toBe(Permission::query()->count());
});

it('approves a direct permission check for a permission created after the seed run', function (): void {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, PermissionRoleSeeder::class]);

    $user = User::factory()->create();
    $user->assignRole(User::SUPERADMIN_ROLE);
    $user = $user->fresh();

    Permission::query()->create([
        'name' => 'students.edit',
        'module' => 'students',
        'action' => 'edit',
    ]);

    expect($user->hasPermissionTo('students.edit'))->toBeTrue();
});

it('resolves a direct permission check the same way as a Gate check', function (): void {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, PermissionRoleSeeder::class]);

    $user = User::factory()->create();
    $user->assignRole(User::SUPERADMIN_ROLE);
    $user = $user->fresh();

    expect($user->hasPermissionTo('permissions.edit'))->toBeTrue()
        ->and($user->can('create', PermissionEntity::class))->toBeTrue();
});

it('does not grant unconditional access to a non-Superadmin role', function (): void {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, PermissionRoleSeeder::class]);

    $user = User::factory()->create();
    $user->assignRole('Consulta');
    $user = $user->fresh();

    expect($user->isSuperadmin())->toBeFalse()
        ->and($user->hasPermissionTo('permissions.edit'))->toBeFalse();
});
