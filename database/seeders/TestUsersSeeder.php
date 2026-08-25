<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One login per seeded role, purely for manually verifying that
 * authorization actually drives rendering (menu items, buttons, forbidden
 * screens) — not for anything in the acceptance criteria. Every user shares
 * the same password as the existing `test@example.com` Superadmin
 * (DatabaseSeeder::run()) so QA only has to remember one password and swap
 * emails to compare what each role sees.
 *
 * Must run after RoleSeeder + PermissionRoleSeeder (the roles and their
 * grants need to exist already). Safe to run standalone / more than once:
 * users are looked up by email via firstOrCreate(), and syncRoles() is
 * idempotent.
 *
 *   php artisan db:seed --class=TestUsersSeeder
 */
class TestUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Superadmin is deliberately excluded — DatabaseSeeder::run() already
        // seeds test@example.com for it, and duplicating that here would just
        // create a second, redundant Superadmin login.
        $roleNames = Role::query()
            ->where('name', '!=', User::SUPERADMIN_ROLE)
            ->pluck('name');

        if ($roleNames->isEmpty()) {
            $this->command->warn('TestUsersSeeder needs RoleSeeder run first.');

            return;
        }

        foreach ($roleNames as $roleName) {
            $email = 'test+'.Str::slug($roleName).'@example.com';

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    // No parentheses: User::initials() (Str::initials()) takes
                    // the first character of every space-separated word, so
                    // "Test (Docente)" would read its avatar initials off the
                    // literal "(" character instead of the role name.
                    'name' => "Test {$roleName}",
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $user->syncRoles([$roleName]);
        }

        $this->command->info(sprintf(
            'Test users seeded: %d roles, all under password "password" — see email test+<role-slug>@example.com.',
            $roleNames->count(),
        ));
    }
}
