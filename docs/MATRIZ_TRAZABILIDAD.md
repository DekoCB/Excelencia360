# Matriz de trazabilidad

Complementa `docs/INFORME_FINAL.md` (sección 14). Sigue la estructura de la
sección 51 del prompt maestro: **Requerimiento → Módulo → Ruta → Service/
Componente → Modelo/Tabla → Test**, agrupando por dominio de negocio en vez
de por cada una de las 57 secciones individuales (varias secciones del
prompt maestro describen distintos aspectos del mismo módulo real).

Notas de lectura:
- La arquitectura es Laravel + **Livewire/Volt** (Blade + PHP en un solo
  archivo), no Laravel + Controllers tradicionales — el Volt hace de
  Controller y de View a la vez. La columna "Service/Componente" nombra el
  archivo Livewire (`resources/views/livewire/...`) y, cuando existe, el
  Service que contiene la lógica de negocio detrás.
- **Estado**: ✅ Cumplido (interfaz + backend + validación + regla de
  negocio + base de datos + autorización + manejo de errores + prueba,
  criterio de la sección 57) · 🟡 Parcial (falta algo de lo anterior,
  detallado en la columna Nota) · — No aplica al alcance actual.
- "Test" nombra la carpeta real (`tests/Feature/<Módulo>/`), no cada clase
  individual — varios módulos tienen más de un archivo de test.

| # | Requerimiento (§ prompt maestro) | Módulo | Estado | Ruta(s) | Service / Componente | Modelo(s) / Tabla(s) | Test |
|---|---|---|---|---|---|---|---|
| 1 | Estudiantes y clasificación académica (§6, §7) | Matrícula / Académico | ✅ | `matricula.index`, `matricula.show`, `academico.grados.index`, `academico.cursos.index`, `academico.ciclos.index` | `MatriculaService`, `HorarioService` | `Estudiante` / `estudiantes`, `Grado`, `Ciclo`, `Curso` | `tests/Feature/Matricula/`, `tests/Feature/Academico/` |
| 2 | Docentes (§8) | Docentes | ✅ | `docentes.index`, `docentes.carga-masiva`, `contratos.index` | `DocenteService` | `Docente` / `docentes`, `Contrato` / `contratos` | `tests/Feature/Docentes/` |
| 3 | Personal administrativo (§9) | Personal | ✅ | `personal.index`, `personal.carga-masiva` | `PersonalService` | `Personal` / `personal` | `tests/Feature/Personal/` |
| 4 | Cursos, asignaturas y horarios (§10, §12) | Académico | ✅ | `academico.cursos.index`, `academico.horarios.index`, `academico.aulas.index`, `academico.siagie.index` | `HorarioService` | `Curso`, `Horario`, `HorarioDia`, `Aula`, `Siagie` | `tests/Feature/Academico/` |
| 5 | Matrículas (§11) | Matrícula | ✅ | `matricula.index`, `matricula.show`, `matricula.carga-masiva` | `MatriculaService` | `Matricula` / `matriculas` | `tests/Feature/Matricula/` |
| 6 | Padres y apoderados (§16) | Matrícula (ampliado hoy) | ✅ | `matricula.mis-hijos` | `MatriculaService::registrarApoderado()` | `Apoderado` / `apoderados` (+ `user_id`) | `tests/Feature/Matricula/MisHijosPermisosTest.php`, `GenerarAccesosApoderadosCommandTest.php` |
| 7 | Asistencia — estudiantes y docentes (§13) | Asistencia + AsistenciaDocentes (nuevo hoy) | ✅ | `asistencia.index`, `asistencia.marcar`, `asistencia.show`, `asistencia-docentes.index` | `AsistenciaService`, `AsistenciaDocenteService` | `Asistencia` / `asistencias`, `AsistenciaDocente` / `asistencias_docentes` | `tests/Feature/Asistencia/`, `tests/Feature/AsistenciaDocentes/` |
| 8 | Calificaciones y evaluaciones (§14, §15) | Evaluaciones | ✅ | `evaluaciones.index`, `evaluaciones.show`, `evaluaciones.libreta`, `evaluaciones.mi-libreta` | `EvaluacionService`, `LibretaService` | `Evaluacion` / `evaluaciones`, `Calificacion` | `tests/Feature/Evaluaciones/` |
| 9 | Dashboard administrativo (§17) | Dashboard | ✅ | `dashboard` | — (agrega datos de otros módulos) | — | `tests/Feature/Dashboard/` |
| 10 | Notificaciones (§18) | Notificaciones | 🟡 | `notificaciones.index`, `notificaciones.mis-mensajes`, `notificaciones.plantillas` | `NotificacionService` | `Notificacion`, `CampaniaWhatsapp`, `PlantillaWhatsapp` | `tests/Feature/Notificaciones/` |
| 11 | Calendario académico (§19) | Calendario (nuevo hoy) | ✅ | `calendario.index` | `CalendarioService` (reutiliza `EvaluacionService`) | `EventoCalendario` / `eventos_calendario` | `tests/Feature/Calendario/` |
| 12 | Documentos, PDFs y verificación (§20, §27, §34) | Certificados / Constancias (ampliado hoy) | ✅ | `certificados.index`, `certificados.mis-certificados`, `certificados.verificar`, `constancias.index`, `constancias.mis-constancias` | `CertificadoService`, `App\Shared\Support\QrCode` | `Certificado` / `certificados` | `tests/Feature/Certificados/`, `tests/Unit/Shared/QrCodeTest.php` |
| 13 | Usuarios, roles y auditoría (§21, §26, §41) | Identidad | ✅ | `roles.index`, `usuarios.index`, `usuarios.show`, `auditoria.index`, `historial-contrasenas.index`, `two-factor.challenge` | `RolesAndPermissionsSeeder`, `TwoFactorAuthenticationService`, `AuditService` | `Role`/`Permission` (Spatie), `AuditLog`, `RegistroIngreso` | `tests/Feature/Identidad/`, `tests/Feature/Auth/` |
| 14 | Caja y finanzas, conceptos de pago (§22, §24) | Pagos / Flujo de Caja | ✅ | `pagos.index`, `pagos.conceptos`, `pagos.cuentas-bancarias`, `pagos.mi-cuenta`, `flujo-caja.index` | `CobranzaService` | `ConceptoPago`, `PlanPago`, `Cuota`, `Recibo`, `Egreso` | `tests/Feature/Pagos/`, `tests/Feature/FlujoCaja/` |
| 15 | FUT — solicitudes (§23) | Trámites (nuevo hoy) | ✅ | `tramites.index` | `TramiteService` | `SolicitudTramite` / `solicitudes_tramite` | `tests/Feature/Tramites/` |
| 16 | Búsqueda avanzada (§25) | Busqueda (global, nuevo) + Matrícula (filtros avanzados, ampliado) | ✅ | widget embebido en el topbar (búsqueda global, sin ruta propia); `matricula.index` con "Búsqueda avanzada" (ciclo/grado/curso/docente) | `BusquedaGlobalService`; `FiltroMatriculaAcademico` (`app/Modules/Academico/Support`, compartido con Reportes) | — (búsqueda global lee `estudiantes`/`apoderados`/`users`/`personal`); filtros avanzados vía `matriculas`/`horarios` | `tests/Feature/Busqueda/`, `tests/Feature/Academico/FiltroMatriculaAcademicoTest.php`, `tests/Feature/Matricula/MatriculaPermisosTest.php` |
| 17 | Portal del estudiante y del docente (§31, §32) | Transversal (mismo panel, filtrado por rol) | ✅ | `evaluaciones.mi-libreta`, `pagos.mi-cuenta`, `certificados.mis-certificados`, `constancias.mis-constancias`, `asistencia.index` (vista propia) | Services de cada módulo, con alcance `*_propio` | — | Tests de permisos de cada módulo (`*PermisosTest.php`) |
| 18 | Biblioteca (§35) | Biblioteca (nuevo hoy) | ✅ | `biblioteca.index`, `biblioteca.mis-prestamos` | `BibliotecaService` | `Libro`/`libros`, `Ejemplar`/`ejemplares`, `Prestamo`/`prestamos` | `tests/Feature/Biblioteca/` |
| 19 | Aula virtual (§36) | AulaVirtual | ✅ | `aula-virtual.index`, `aula-virtual.show`, `aula-virtual.tarea` | — | `CursoVirtual`, `Material`, `Tarea`, `EntregaTarea`, `Foro` | `tests/Feature/AulaVirtual/` |
| 20 | Sitio institucional e importación/exportación Excel (§33, §28) | Landing / transversal | ✅ | `/`, `cursos/{slug}`, `matricula.carga-masiva`, `docentes.carga-masiva`, `personal.carga-masiva` | `App\Shared\Support\ImportaFilasDeExcel` | — | `tests/Feature/Landing/` |

---

## Cómo mantenerla al día

Cada vez que se agregue o cambie un módulo: agregar (o actualizar) una
fila aquí con datos reales — ruta verificada con `php artisan route:list`,
carpeta de test verificada con `ls tests/Feature/`, nunca por memoria. Si
un requerimiento pasa de 🟡 a ✅, actualizar también la sección 4 y 11 de
`docs/INFORME_FINAL.md`.
