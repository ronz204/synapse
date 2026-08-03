<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        foreach ($permissions as $name => $description) {
            Permission::query()->firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
