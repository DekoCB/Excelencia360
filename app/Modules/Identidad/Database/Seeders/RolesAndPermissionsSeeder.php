<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Database\Seeders;

use App\Shared\Enums\RolEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siembra los 6 roles institucionales y la matriz de permisos base descrita
 * en el documento de arquitectura (sección 05). Los permisos siguen la
 * convención modulo.accion y son editables luego desde la UI de Roles.
 *
 * Coordinador concentra la operación de matrícula, cobranza y
 * certificaciones además de la supervisión académica: en un CEBA la misma
 * persona suele cubrir estos tres frentes, por lo que no se modelan como
 * roles institucionales separados.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const MATRIZ = [
        RolEnum::DIRECCION->value => ['*'],

        RolEnum::COORDINADOR->value => [
            'academico.ver', 'academico.gestionar',
            'matricula.crear', 'matricula.ver', 'matricula.editar', 'matricula.anular',
            'docentes.ver', 'docentes.gestionar',
            'contratos.ver', 'contratos.gestionar',
            'personal.ver', 'personal.gestionar',
            'migraciones.ver', 'migraciones.gestionar',
            'vacaciones.ver', 'vacaciones.gestionar',
            'aula_virtual.ver', 'aula_virtual.gestionar_propio',
            'evaluaciones.ver', 'evaluaciones.publicar',
            'asistencia.ver',
            'incidencias.ver', 'incidencias.crear',
            'pagos.ver', 'pagos.gestionar',
            'certificados.ver', 'certificados.emitir', 'certificados.duplicar', 'certificados.gestionar_plantilla',
            'reportes.academicos', 'reportes.matricula', 'reportes.financieros', 'reportes.morosos', 'reportes.certificados', 'reportes.historial_estudiante', 'reportes.exportar',
            'whatsapp.ver', 'whatsapp.enviar',
            'flujo_caja.ver',
            'tramites.crear', 'tramites.ver_propio', 'tramites.gestionar',
            'calendario.ver', 'calendario.gestionar',
            'asistencia_docentes.ver', 'asistencia_docentes.registrar',
            'biblioteca.ver', 'biblioteca.gestionar',
        ],

        RolEnum::ADMINISTRATIVO->value => [
            'matricula.ver',
            'pagos.registrar',
            'asistencia.ver',
            'reportes.operativos', 'reportes.exportar',
            'whatsapp.ver', 'whatsapp.enviar',
            'usuarios.ver',
            'usuarios.gestionar_sesiones',
            'auditoria.ver',
            'tramites.crear', 'tramites.ver_propio', 'tramites.gestionar',
            'calendario.ver', 'calendario.gestionar',
            'asistencia_docentes.ver', 'asistencia_docentes.registrar',
            'biblioteca.ver', 'biblioteca.gestionar',
        ],

        RolEnum::TESORERIA->value => [
            'pagos.ver', 'pagos.aprobar', 'pagos.rechazar',
            'tesoreria.gestionar',
            'reportes.financieros', 'reportes.morosos', 'reportes.exportar',
            'flujo_caja.ver', 'flujo_caja.gestionar',
            'tramites.crear', 'tramites.ver_propio',
            'calendario.ver',
            'biblioteca.ver',
        ],

        RolEnum::DOCENTE->value => [
            'aula_virtual.gestionar_propio',
            'evaluaciones.registrar',
            'asistencia.registrar',
            'incidencias.gestionar_propio',
            'reportes.propios', 'reportes.exportar',
            'tramites.crear', 'tramites.ver_propio',
            'calendario.ver',
            'asistencia_docentes.ver_propio',
            'biblioteca.ver', 'biblioteca.ver_propio',
        ],

        RolEnum::ESTUDIANTE->value => [
            'aula_virtual.ver_propio',
            'evaluaciones.ver_propio',
            'asistencia.ver_propio',
            'incidencias.ver_propio',
            'pagos.ver_propio', 'pagos.subir_comprobante',
            'certificados.solicitar',
            'notificaciones.ver_propio',
            'tramites.crear', 'tramites.ver_propio',
            'calendario.ver',
            'biblioteca.ver', 'biblioteca.ver_propio',
        ],

        // Solo lectura sobre el/los hijo(s) vinculados a su cuenta (ver
        // Apoderado::user_id) -- reutiliza el mismo resumen de solo
        // lectura que ya usa Coordinador/Dirección
        // (HistorialEstudianteService), sin el buscador general.
        RolEnum::APODERADO->value => [
            'matricula.ver_propio_hijo',
            'tramites.crear', 'tramites.ver_propio',
            'calendario.ver',
        ],
    ];

    /**
     * @var list<string>
     */
    private const TODOS_LOS_PERMISOS = [
        'academico.ver', 'academico.gestionar',
        'matricula.crear', 'matricula.ver', 'matricula.editar', 'matricula.anular', 'matricula.ver_propio_hijo',
        'docentes.ver', 'docentes.gestionar',
        'contratos.ver', 'contratos.gestionar',
        'personal.ver', 'personal.gestionar',
        'migraciones.ver', 'migraciones.gestionar',
        'vacaciones.ver', 'vacaciones.gestionar',
        'aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio',
        'evaluaciones.ver', 'evaluaciones.registrar', 'evaluaciones.publicar', 'evaluaciones.ver_propio',
        'asistencia.ver', 'asistencia.registrar', 'asistencia.ver_propio',
        'incidencias.ver', 'incidencias.crear', 'incidencias.gestionar_propio', 'incidencias.ver_propio',
        'pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar', 'pagos.aprobar_montos',
        'pagos.ver_propio', 'pagos.subir_comprobante',
        'tesoreria.gestionar',
        'certificados.emitir', 'certificados.duplicar', 'certificados.ver', 'certificados.solicitar', 'certificados.gestionar_plantilla',
        'reportes.academicos', 'reportes.operativos', 'reportes.financieros', 'reportes.morosos',
        'reportes.matricula', 'reportes.certificados', 'reportes.propios', 'reportes.historial_estudiante', 'reportes.exportar',
        'whatsapp.enviar', 'whatsapp.ver', 'notificaciones.ver_propio',
        'flujo_caja.ver', 'flujo_caja.gestionar',
        'roles.gestionar', 'auditoria.ver',
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.gestionar_sesiones',
        'tramites.crear', 'tramites.ver_propio', 'tramites.gestionar',
        'calendario.ver', 'calendario.gestionar',
        'asistencia_docentes.ver', 'asistencia_docentes.registrar', 'asistencia_docentes.ver_propio',
        'biblioteca.ver', 'biblioteca.gestionar', 'biblioteca.ver_propio',
    ];

    public function run(): void
    {
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::TODOS_LOS_PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        foreach (self::MATRIZ as $rol => $permisos) {
            $role = Role::findOrCreate($rol, 'web');

            if ($permisos === ['*']) {
                $role->syncPermissions(self::TODOS_LOS_PERMISOS);

                continue;
            }

            $role->syncPermissions($permisos);
        }
    }
}
