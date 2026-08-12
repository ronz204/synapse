<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * `module` and `action` are both NOT NULL since the migration that split
     * them out of `name`, and `name` stays as their "module.action" join so the
     * three columns can never disagree.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->unique()->word();
        $action = fake()->randomElement(['view', 'create', 'edit', 'delete', 'export_pdf', 'export_excel']);

        return [
            'module' => $module,
            'action' => $action,
            'name' => $module.'.'.$action,
            'description' => fake()->sentence(),
        ];
    }
}
