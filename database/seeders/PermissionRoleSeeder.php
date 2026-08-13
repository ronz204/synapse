<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Role -> permissions matrix (siga.sql §9.4). Mapped by name, not numeric
     * id, so it doesn't depend on RoleSeeder/PermissionSeeder's insertion order.
     */
    public function run(): void
    {
        // The three roles that already hold the coarse `planes.gestionar` also
        // get the granular RC-01 permissions (courses.*, study_plans.*) — same
        // roles, the fine-grained CoursePolicy/StudyPlanPolicy checks these by
        // name instead of the coarse one. The same three roles already hold
        // the coarse `equiparaciones.gestionar` too, so they also get the
        // granular RC-02 permissions (equivalencies.*).
        $coursePermissions = ['courses.view', 'courses.search', 'courses.create', 'courses.edit', 'courses.delete', 'courses.export_pdf', 'courses.export_excel'];
        $studyPlanPermissions = ['study_plans.view', 'study_plans.search', 'study_plans.create', 'study_plans.edit', 'study_plans.export_pdf', 'study_plans.export_excel'];
        $equivalencyPermissions = ['equivalencies.view', 'equivalencies.search', 'equivalencies.create', 'equivalencies.resolve_contradiction', 'equivalencies.export_pdf', 'equivalencies.export_excel'];

        $matrix = [
            'Administrador' => [
                'atestados.gestionar', 'catalogo.gestionar', 'oferta.gestionar', 'atinencia.verificar',
                'nota_tecnica.aprobar', 'oferta.consultar', 'usuarios.gestionar', 'archivos.subir',
                'archivos.descargar', 'resoluciones.gestionar', 'reservas.gestionar', 'oferta.consolidar',
                'planes.gestionar', 'equiparaciones.gestionar', 'solicitudes.crear', 'solicitudes.revisar',
                ...$coursePermissions, ...$studyPlanPermissions, ...$equivalencyPermissions,
            ],
            'Coordinadora de Docencia' => [
                'atestados.gestionar', 'oferta.gestionar', 'atinencia.verificar', 'oferta.consultar',
                'archivos.subir', 'archivos.descargar', 'resoluciones.gestionar', 'reservas.gestionar',
                'oferta.consolidar', 'planes.gestionar', 'equiparaciones.gestionar', 'solicitudes.revisar',
                ...$coursePermissions, ...$studyPlanPermissions, ...$equivalencyPermissions,
            ],
            'Docente' => ['oferta.consultar', 'archivos.descargar'],
            'Consulta' => ['oferta.consultar'],
            'Director de Carrera' => [
                'oferta.gestionar', 'oferta.consultar', 'archivos.subir', 'archivos.descargar',
                'resoluciones.gestionar', 'planes.gestionar', 'equiparaciones.gestionar',
                ...$coursePermissions, ...$studyPlanPermissions, ...$equivalencyPermissions,
            ],
            'Coordinador CONTA' => ['oferta.consultar', 'archivos.descargar', 'oferta.consolidar'],
            'Recursos Humanos' => ['oferta.consultar', 'archivos.descargar'],
            'Estudiante' => ['solicitudes.crear', 'archivos.subir'],
            'Comisión Técnica' => ['solicitudes.revisar', 'archivos.descargar'],
        ];

        foreach ($matrix as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id', 'id');

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
