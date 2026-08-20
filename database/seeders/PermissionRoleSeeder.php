<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
        // granular RC-02 permissions (equivalencies.*). They also already
        // hold the coarse `resoluciones.gestionar`, so they get the granular
        // RC-03 permissions too (modalities.*, modality_resolutions.*).
        $coursePermissions = ['courses.view', 'courses.search', 'courses.create', 'courses.edit', 'courses.delete', 'courses.export_pdf', 'courses.export_excel'];
        $studyPlanPermissions = ['study_plans.view', 'study_plans.search', 'study_plans.create', 'study_plans.edit', 'study_plans.export_pdf', 'study_plans.export_excel'];
        $equivalencyPermissions = ['equivalencies.view', 'equivalencies.search', 'equivalencies.create', 'equivalencies.resolve_contradiction', 'equivalencies.export_pdf', 'equivalencies.export_excel'];
        $modalityPermissions = ['modalities.view', 'modalities.search', 'modalities.create', 'modalities.edit', 'modalities.delete', 'modalities.export_pdf', 'modalities.export_excel'];
        $modalityResolutionPermissions = ['modality_resolutions.view', 'modality_resolutions.search', 'modality_resolutions.create', 'modality_resolutions.export_pdf', 'modality_resolutions.export_excel'];
        // RC-02b's history is the readable side of the equivalencies these
        // same three roles already register, so it follows the same grant.
        $academicRecordPermissions = ['academic_records.view', 'academic_records.search'];

        $matrix = [
            'Administrador' => [
                'atestados.gestionar', 'catalogo.gestionar', 'oferta.gestionar', 'atinencia.verificar',
                'nota_tecnica.aprobar', 'oferta.consultar', 'usuarios.gestionar', 'archivos.subir',
                'archivos.descargar', 'resoluciones.gestionar', 'reservas.gestionar', 'oferta.consolidar',
                'planes.gestionar', 'equiparaciones.gestionar', 'solicitudes.crear', 'solicitudes.revisar',
                ...$coursePermissions, ...$studyPlanPermissions, ...$equivalencyPermissions,
                ...$modalityPermissions, ...$modalityResolutionPermissions, ...$academicRecordPermissions,
            ],
            'Coordinadora de Docencia' => [
                'atestados.gestionar', 'oferta.gestionar', 'atinencia.verificar', 'oferta.consultar',
                'archivos.subir', 'archivos.descargar', 'resoluciones.gestionar', 'reservas.gestionar',
                'oferta.consolidar', 'planes.gestionar', 'equiparaciones.gestionar', 'solicitudes.revisar',
                ...$coursePermissions, ...$studyPlanPermissions, ...$equivalencyPermissions,
                ...$modalityPermissions, ...$modalityResolutionPermissions, ...$academicRecordPermissions,
            ],
            'Docente' => ['oferta.consultar', 'archivos.descargar'],
            'Consulta' => ['oferta.consultar'],
            'Director de Carrera' => [
                'oferta.gestionar', 'oferta.consultar', 'archivos.subir', 'archivos.descargar',
                'resoluciones.gestionar', 'planes.gestionar', 'equiparaciones.gestionar',
                ...$coursePermissions, ...$studyPlanPermissions, ...$equivalencyPermissions,
                ...$modalityPermissions, ...$modalityResolutionPermissions, ...$academicRecordPermissions,
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

        $this->syncSuperadmin();
    }

    /**
     * Superadmin is absent from the matrix above because it takes every
     * permission rather than a hand-picked set. This lives here, not in
     * RoleSeeder, so it runs after PermissionSeeder has created the rows —
     * syncing from RoleSeeder would silently grant nothing.
     *
     * DomainServiceProvider's Gate::before still covers permissions created
     * after the last seed run; this keeps the pivot honest for anything that
     * reads the relation directly, such as the roles screen's permission count.
     */
    private function syncSuperadmin(): void
    {
        $superadmin = Role::query()
            ->where('name', User::SUPERADMIN_ROLE)
            ->firstOrFail();

        $superadmin->permissions()->sync(Permission::query()->pluck('id'));
    }
}
