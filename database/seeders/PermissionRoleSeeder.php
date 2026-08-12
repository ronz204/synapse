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
        $matrix = [
            'Administrador' => [
                'atestados.gestionar', 'catalogo.gestionar', 'oferta.gestionar', 'atinencia.verificar',
                'nota_tecnica.aprobar', 'oferta.consultar', 'usuarios.gestionar', 'archivos.subir',
                'archivos.descargar', 'resoluciones.gestionar', 'reservas.gestionar', 'oferta.consolidar',
                'planes.gestionar', 'equiparaciones.gestionar', 'solicitudes.crear', 'solicitudes.revisar',
            ],
            'Coordinadora de Docencia' => [
                'atestados.gestionar', 'oferta.gestionar', 'atinencia.verificar', 'oferta.consultar',
                'archivos.subir', 'archivos.descargar', 'resoluciones.gestionar', 'reservas.gestionar',
                'oferta.consolidar', 'planes.gestionar', 'equiparaciones.gestionar', 'solicitudes.revisar',
            ],
            'Docente' => ['oferta.consultar', 'archivos.descargar'],
            'Consulta' => ['oferta.consultar'],
            'Director de Carrera' => [
                'oferta.gestionar', 'oferta.consultar', 'archivos.subir', 'archivos.descargar',
                'resoluciones.gestionar', 'planes.gestionar', 'equiparaciones.gestionar',
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
