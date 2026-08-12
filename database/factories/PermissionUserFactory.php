<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionUser>
 */
class PermissionUserFactory extends Factory
{
    protected $model = PermissionUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'permission_id' => Permission::factory(),
            'granted_by' => User::factory(),
        ];
    }
}
