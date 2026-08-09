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
     * Permisos base del RBAC (siga.sql §9.4).
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

        // Permisos por operación del módulo IdentityAccess. Las políticas de Role
        // y Permission los comprueban por nombre, y las vistas hacen lo mismo en
        // modo cliente, donde no hay entidad contra la que autorizar.
        foreach (['roles', 'permissions'] as $module) {
            $subject = $module === 'roles' ? 'roles' : 'permisos';

            $permissions += [
                "{$module}.view" => "Consultar {$subject}",
                "{$module}.search" => "Buscar {$subject}",
                "{$module}.create" => "Crear {$subject}",
                "{$module}.edit" => "Editar {$subject}",
                "{$module}.delete" => "Eliminar {$subject}",
                "{$module}.export_pdf" => "Exportar {$subject} a PDF",
                "{$module}.export_excel" => "Exportar {$subject} a Excel",
            ];
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
