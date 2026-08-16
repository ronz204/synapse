<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Base RBAC roles (siga.sql §9.4).
     */
    public function run(): void
    {
        $roles = [
            'Administrador' => 'Gestión total: catálogo de atinencias, usuarios y configuración',
            'Coordinadora de Docencia' => 'Registra atestados, consolida y gestiona asignaciones docentes',
            'Docente' => 'Consulta su perfil, atestados y asignaciones',
            'Consulta' => 'Acceso de solo lectura a la oferta académica',
            'Director de Carrera' => 'Registra la oferta, planes y resoluciones de su propia carrera',
            'Coordinador CONTA' => 'Consolida la oferta de las carreras de su área',
            'Recursos Humanos' => 'Lectura de la oferta consolidada; sin acceso a atinencias',
            'Estudiante' => 'Presenta y da seguimiento a sus propias solicitudes',
            'Comisión Técnica' => 'Revisa y resuelve solicitudes de convalidación',
        ];

        foreach ($roles as $name => $description) {
            Role::query()->firstOrCreate(['name' => $name], ['description' => $description]);
        }

        $this->seedSuperadmin();
    }

    /**
     * Superadmin is a technical role, deliberately kept out of the list above:
     * every entry there maps to a real position at the university, while this
     * one exists so somebody can always administer the system.
     *
     * Granting it the permissions themselves belongs to PermissionRoleSeeder,
     * which owns the role -> permission wiring and runs after PermissionSeeder.
     * Doing it here would silently sync an empty set, since this seeder runs
     * before any permission row exists.
     */
    private function seedSuperadmin(): void
    {
        Role::query()->firstOrCreate(
            ['name' => User::SUPERADMIN_ROLE],
            ['description' => 'Rol técnico con acceso incondicional a todo el sistema'],
        );
    }
}
