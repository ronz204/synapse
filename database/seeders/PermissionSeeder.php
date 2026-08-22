<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Base RBAC permissions (siga.sql §9.4).
     */
    public function run(): void
    {
        $permissions = [
            'atestados.gestionar' => 'Crear y editar atestados de docentes',
            'catalogo.gestionar' => 'Crear versiones del catálogo de atinencias',
            'oferta.gestionar' => 'Crear grupos, horarios y asignaciones',
            'atinencia.verificar' => 'Ejecutar verificaciones de atinencia',
            'nota_tecnica.aprobar' => 'Aprobar la vía excepcional de Nota Técnica',
            'oferta.consultar' => 'Consultar la oferta académica',
            'usuarios.gestionar' => 'Administrar usuarios, roles y permisos',
            'archivos.subir' => 'Adjuntar documentos a los módulos',
            'archivos.descargar' => 'Descargar documentos adjuntos y reportes',
            'resoluciones.gestionar' => 'Registrar resoluciones de modalidad por curso',
            'reservas.gestionar' => 'Registrar y aprobar préstamos de aulas',
            'oferta.consolidar' => 'Consolidar la oferta y mover grupos de estado',
            'planes.gestionar' => 'Administrar planes de estudio, niveles y requisitos',
            'equiparaciones.gestionar' => 'Registrar equiparaciones entre planes',
            'solicitudes.crear' => 'Presentar solicitudes estudiantiles',
            'solicitudes.revisar' => 'Revisar y resolver solicitudes estudiantiles',
        ];

        // Per-operation permissions for granular CRUD modules. Each module's
        // policy checks these by name, and the views do the same in client
        // mode, where there's no entity to authorize against yet.
        //
        // study_plans has no `.delete`: a StudyPlan has no `active` column and
        // this slice offers no destructive/deactivation action for it — only
        // create/edit (including a classification transition). courses does
        // have `.delete`, wired to a deactivation use case, not a row delete.
        // equivalencies has neither `.edit` nor `.delete` (an Equivalency is
        // append-only), but has its own `.resolve_contradiction`, distinct
        // from `.create` — registering and resolving a contradiction are
        // separate authorizations per the RC-02 spec. modalities is a real
        // catalog with a real `.delete` (guarded by an in-use check).
        // modality_resolutions has neither `.edit` nor `.delete` (a
        // resolution is never edited or deleted once filed) — its `.create`
        // covers the combined "assign a modality (with its resolution) to a
        // course" action, a separate authorization from modalities.* per the
        // RC-03 spec.
        $moduleSubjects = [
            'roles' => 'roles',
            'permissions' => 'permisos',
            'courses' => 'cursos',
            'study_plans' => 'planes de estudio',
            'equivalencies' => 'equiparaciones',
            'modalities' => 'modalidades',
            'modality_resolutions' => 'resoluciones de modalidad',
            'academic_records' => 'historiales académicos',
        ];

        $standardActions = ['view', 'search', 'create', 'edit', 'delete', 'export_pdf', 'export_excel'];
        $moduleActions = [
            'study_plans' => ['view', 'search', 'create', 'edit', 'export_pdf', 'export_excel'],
            'equivalencies' => ['view', 'search', 'create', 'resolve_contradiction', 'export_pdf', 'export_excel'],
            'modality_resolutions' => ['view', 'search', 'create', 'export_pdf', 'export_excel'],
            // Create is limited to manually recording a Passed input. The
            // Accreditation context remains the only writer of grants.
            'academic_records' => ['view', 'search', 'create'],
        ];

        $actionDescriptions = [
            'view' => 'Consultar %s',
            'search' => 'Buscar %s',
            'create' => 'Crear %s',
            'edit' => 'Editar %s',
            'delete' => 'Eliminar %s',
            'resolve_contradiction' => 'Resolver contradicciones de %s',
            'export_pdf' => 'Exportar %s a PDF',
            'export_excel' => 'Exportar %s a Excel',
        ];

        foreach ($moduleSubjects as $module => $subject) {
            foreach ($moduleActions[$module] ?? $standardActions as $action) {
                $permissions["{$module}.{$action}"] = sprintf($actionDescriptions[$action], $subject);
            }
        }

        // This seeder runs WithoutModelEvents, so Permission's saving hook never
        // fires here — module and action have to be supplied explicitly.
        foreach ($permissions as $name => $description) {
            Permission::query()->firstOrCreate(['name' => $name], [
                'module' => Str::before($name, '.'),
                'action' => Str::after($name, '.'),
                'description' => $description,
            ]);
        }
    }
}
