# Informe final — Adaptación de Excelencia 360 al prompt maestro

Generado el 2026-09-15, al cierre de los 7 puntos que la auditoría inicial
(Fases 1-3 del "PROMPT MAESTRO — ADAPTACIÓN Y DESARROLLO DEL SISTEMA
EXCELENCIA 360") marcó como faltantes o parciales. Sigue la estructura
exigida en la sección 56 del propio documento.

---

## 1. Resumen ejecutivo

El sistema **no partió de cero**: ya era un ERP académico modular maduro
(Laravel 12 + Livewire/Volt, arquitectura por módulos en `app/Modules/*`,
899 tests en verde) construido para el modelo EBA (Educación Básica
Alternativa) y rebrandeado a Excelencia 360 días antes de este cierre. La
auditoría inicial encontró que **~70% de los 57 requisitos del prompt
maestro ya existía o estaba parcial**, y que 6 puntos faltaban por
completo: rol/portal de Apoderados, verificación de documentos por QR,
FUT/Trámites genérico, Calendario académico unificado, búsqueda global
entre entidades, y Biblioteca. Un séptimo punto (asistencia de docentes,
distinta de la asistencia de estudiantes que ya existía) se identificó
como brecha adicional durante la propia ejecución del roadmap.

Los 7 puntos se construyeron el mismo día (2026-09-15), cada uno siguiendo
el mismo método: analizar el código real antes de escribir nada, reutilizar
servicios y patrones ya existentes en vez de duplicar lógica, escribir
pruebas reales (no solo "debería funcionar"), verificar en vivo contra la
base de datos de desarrollo, y documentar el porqué de cada decisión en
`docs/BITACORA.md`. El suite de tests pasó de 899 a 1035 con el cierre de
los 7 puntos, y sigue creciendo con el trabajo posterior de este mismo
día: la fase 11 (optimización) y el cierre completo de la búsqueda
avanzada (sección 25) -- ver la cifra final en la sección 9. Ninguna
regresión se detectó en ningún punto del proceso.

Este informe se generó al cierre de los 7 puntos y se actualizó el mismo
día al completar la fase 11 y la búsqueda avanzada multi-filtro, que en
la primera versión de este documento quedaban explícitamente pendientes
(ver el historial de `docs/BITACORA.md`) -- no se reescribió la
narrativa original, se corrigieron las secciones afectadas para
reflejar el estado real.

---

## 2. Arquitectura implementada

Se mantuvo, sin cambios, el patrón ya establecido antes de este cierre:

```text
Routes (por módulo, app/Modules/<Nombre>/Routes/web.php)
   ↓
Livewire/Volt (hace las veces de Controller + View)
   ↓
Services (app/Modules/<Nombre>/Services/*.php)
   ↓
Eloquent Models (app/Modules/<Nombre>/Models/*.php)
   ↓
MySQL
```

Con una diferencia deliberada frente a la sección 3 del prompt maestro:
el stack no usa **TypeScript** (sección 38) — el frontend es Blade +
Livewire/Volt + Alpine.js, con JavaScript vainilla mínimo. Esta es una
decisión que se le presentó explícitamente al usuario (migrar el paradigma
completo del frontend ya construido y probado, vs. mantenerlo) y que el
usuario tomó a favor de mantener Livewire. No es un incumplimiento por
omisión: está documentado en la memoria del proyecto como decisión
consciente, revisable si en el futuro aparece lógica de cliente
genuinamente compleja que lo justifique.

Cada módulo nuevo de hoy siguió exactamente la misma forma que los módulos
existentes: `Models/`, `Services/`, `Enums/` cuando aplica,
`Database/{Migrations,Factories}/`, `Providers/<Nombre>ServiceProvider.php`
(registrado en `bootstrap/providers.php`), `Routes/web.php`. Dos módulos
(`Busqueda`) no tienen modelo, migración ni tabla propia porque son
agregadores de solo lectura sobre datos de otros módulos — mismo criterio
que ya usaba `Reportes` antes de hoy.

La autorización se resuelve en dos niveles, igual que en el resto del
sistema: permisos `modulo.accion` (Spatie Laravel Permission, sembrados en
`RolesAndPermissionsSeeder.php`) revalidados en el servidor en cada acción
(`abort_unless(...->hasPermissionTo(...), 403)` dentro de cada método
Livewire, no solo ocultando botones), más Policies dedicadas donde el
patrón ya existía (`Asistencia\Policies\HorarioAsistenciaPolicy`). Los
módulos nuevos con reglas de autorización simples (un permiso `.ver`, uno
`.gestionar`) usaron el chequeo inline en vez de una clase Policy aparte —
es el mismo patrón que ya usaban módulos comparables como `Incidencias` y
`Tramites` antes de hoy, no una inconsistencia introducida.

---

## 3. Módulos implementados

### 3.1 Preexistentes, reutilizados sin cambios de fondo

Identidad (roles, permisos, 2FA, auditoría, sesiones activas), Matrícula,
Académico (cursos, horarios, ciclos, integración SIAGIE), Docentes,
Personal, Aula Virtual, Asistencia (de estudiantes), Evaluaciones, Pagos,
Flujo de Caja, Certificados, Reportes, Notificaciones (WhatsApp),
Incidencias, Migraciones, Vacaciones (licencia individual por estudiante),
sitio institucional (Landing).

### 3.2 Nuevos o ampliados hoy

| Módulo | Qué se agregó | Ruta base |
|---|---|---|
| Matrícula (ampliado) | Cuenta de acceso automática para Apoderados + portal `/matricula/mis-hijos` + búsqueda avanzada (ciclo/grado/curso/docente) | `matricula.mis-hijos`, `matricula.index` |
| Certificados (ampliado) | QR de verificación en los 6 tipos de documento | `certificados.verificar` |
| Trámites (nuevo) | FUT genérico con estados y trazabilidad | `tramites.index` |
| Calendario (nuevo) | Vista mensual unificada de clases, evaluaciones y eventos | `calendario.index` |
| AsistenciaDocentes (nuevo) | Control de asistencia laboral diaria del personal docente | `asistencia-docentes.index` |
| Busqueda (nuevo) | Buscador global (topbar) entre Estudiantes/Apoderados/Docentes/Personal | — (widget embebido) |
| Biblioteca (nuevo) | Catálogo, ejemplares y circulación de préstamos | `biblioteca.index`, `biblioteca.mis-prestamos` |
| Academico (ampliado) | `FiltroMatriculaAcademico`: filtro ciclo/grado/curso/docente extraído de Reportes para compartirlo con la búsqueda avanzada, sin duplicar la lógica de "paralelos" | — (helper compartido, sin ruta propia) |

Detalle técnico de cada uno (modelos, bugs reales encontrados, decisiones
de alcance) en `docs/BITACORA.md`, entradas del 2026-09-15.

---

## 4. Requerimientos cumplidos

Ver la matriz de trazabilidad completa (sección 10). En resumen, de los 57
puntos del prompt maestro:

- **Cumplidos por completo** (interfaz + backend + validación + regla de
  negocio + base de datos + autorización + manejo de errores + prueba,
  criterio de la sección 57 del prompt maestro): estudiantes, apoderados,
  docentes, personal, catálogos académicos, matrícula, horarios,
  asistencia (estudiantes y, desde hoy, docentes), evaluaciones y
  calificaciones, pagos y caja, FUT/Trámites, documentos PDF con
  verificación por QR, reportes, portal estudiante, portal docente,
  biblioteca, aula virtual, sitio institucional, roles/permisos,
  auditoría, calendario académico, 2FA, rate limiting, hashing de
  contraseñas, y **búsqueda avanzada** (sección 25 completa: búsqueda
  global simple por nombre/DNI entre entidades, más filtros
  ciclo/grado/curso/docente en el listado de estudiantes).
- **Cumplidos con alcance reducido, de forma deliberada**: notificaciones
  (WhatsApp desacoplado por driver; falta el canal de comunicados
  institucionales por email, ver sección 11).
- **No cumplidos literalmente, por decisión del usuario**: TypeScript
  (sección 38).

---

## 5. Cambios realizados en base de datos

Migraciones nuevas de hoy (todas incrementales, ninguna modificó o
destruyó una tabla existente fuera de lo estrictamente necesario):

```text
2027_01_28_090000  add_user_id_to_apoderados_table
2027_01_29_090000  create_solicitudes_tramite_table
2027_01_30_090000  create_eventos_calendario_table
2027_01_31_090000  create_asistencias_docentes_table
2027_02_01_090000  create_libros_table
2027_02_01_090100  create_ejemplares_table
2027_02_01_090200  create_prestamos_table
2027_02_02_090000  add_categoria_index_to_solicitudes_tramite_table
2027_02_02_090100  add_fecha_index_to_asistencias_docentes_table
```

Las 2 últimas son de la fase 11 (sección 3.2): agregan un índice a una
columna que ya se consultaba pero no tenía uno -- ninguna cambia datos ni
estructura existente. `Busqueda` no agregó tablas propias — lee
`estudiantes`, `apoderados`, `docentes` (vía `users`) y `personal` ya
existentes.

---

## 6. Cambios realizados en frontend

7 páginas Livewire/Volt nuevas (`tramites.index`, `calendario.index`,
`asistencia-docentes.index`, `biblioteca.index`, `biblioteca.mis-prestamos`,
`matricula.mis-hijos`, y el widget `busqueda.buscador-global` embebido en
el topbar), más 7 entradas nuevas en el menú lateral (`sidebar-nav.blade.php`),
cada una detrás de su propio permiso `.ver`/`.ver_propio`. Además, se
completó una tarea visual pendiente de la sesión anterior: se quitó la
mascota astronauta que quedaba en el login/2FA y en el pie del sidebar (ver
`docs/BITACORA.md`, entrada "Se quitó también la mascota astronauta").

Más tarde el mismo día: `matricula.index` (ya existente) ganó un bloque
de "Búsqueda avanzada" desplegable con 4 filtros en cascada
(ciclo→grado→curso, más docente independiente) -- oculto por defecto
para no saturar el formulario simple de nombre/DNI + estado que cubre el
caso de uso más común.

---

## 7. Cambios realizados en backend

7 Services nuevos (`TramiteService`, `CalendarioService`,
`AsistenciaDocenteService`, `BusquedaGlobalService`, `BibliotecaService`,
más los métodos agregados a `MatriculaService` y `CertificadoService`
existentes), 3 Enums de estado, 1 helper compartido nuevo
(`App\Shared\Support\QrCode`), y una relación nueva en el modelo base de
usuario (`User::docente(): HasOne`, que no existía — ya existían
`estudiante()` y `apoderados()`). Más tarde el mismo día:
`App\Modules\Academico\Support\FiltroMatriculaAcademico`, extraído de
`ReporteService` (ver sección 3.2) para la búsqueda avanzada, y las
correcciones de rendimiento de la sección 11.

---

## 8. Seguridad implementada

- Autorización revalidada en servidor en cada acción de los 7 módulos
  nuevos, no solo ocultada en la interfaz — cubierto por tests que llaman
  los métodos directamente sin pasar por la UI (`assertForbidden()` en
  cada suite de permisos).
- Aislamiento de datos por rol probado explícitamente: un Docente no ve
  trámites/calendario/asistencia de otro Docente; un Estudiante no ve
  registros de otro Estudiante; un Apoderado solo ve a sus propios hijos
  incluso manipulando directamente la propiedad pública de Livewire.
- El buscador global respeta los mismos permisos `.ver` de cada entidad —
  no expone nada que el usuario no pudiera ya consultar por su cuenta.
- Ningún dato institucional o de prueba fue inventado; los datos de
  ejemplo (blog, cursos) quedan en `null`/vacíos hasta que existan datos
  reales, como ya regía desde el rebranding.

---

## 9. Pruebas realizadas

| Módulo | Archivo(s) de test | Casos |
|---|---|---|
| Apoderados | `AuthenticationTest`, `MatriculaServiceTest`, `MisHijosPermisosTest`, `GenerarAccesosApoderadosCommandTest` | +14 |
| QR | `QrCodeTest`, `CertificadosPermisosTest`, `CertificadoServiceTest` (ajustes) | 8 |
| Trámites | `TramiteServiceTest`, `TramitesPermisosTest` | 24 |
| Calendario | `CalendarioServiceTest`, `CalendarioPermisosTest` | 30 |
| Asistencia docentes | `AsistenciaDocenteServiceTest`, `AsistenciaDocentesPermisosTest` | 18 |
| Búsqueda global | `BusquedaGlobalServiceTest`, `BuscadorGlobalTest` | 15 |
| Biblioteca | `BibliotecaServiceTest`, `BibliotecaPermisosTest` | 24 |
| Fase 11 (optimización) | 2 N+1 corregidos (adjuntos de Trámites, docente de Calendario) + 2 índices + 3 listas paginadas, cada uno con su propio test de regresión | +5 |
| Búsqueda avanzada (§25) | `FiltroMatriculaAcademicoTest` (filtro por docente, nuevo), más los ajustes en `MatriculaServiceTest`/`MatriculaPermisosTest` (cascada ciclo→grado→curso, toggle) | +9 |

Suite completo del proyecto: **1035/1035** al cierre de los 7 puntos, y
**1049/1049** tras la fase 11 y el cierre de la búsqueda avanzada (+14
casos nuevos, todos en verde). Pint y Larastan (nivel 5) limpios después
de cada entrega, sin una sola regresión detectada en el camino. Cada
módulo se verificó además en vivo contra la base de datos real de
desarrollo (no solo la de tests) vía `tinker`, con limpieza de los datos
de prueba al final de cada verificación.

---

## 10. Problemas encontrados y solucionados

4 bugs reales, los 4 atrapados por el propio proceso (tests o verificación
en vivo) antes de darse por buenos, no reportados por el usuario:

1. **Docblock huérfano en `User.php`** (Apoderados): al agregar
   `apoderados(): HasMany`, dos bloques `/** */` quedaron consecutivos sin
   código entre medio, lo que rompió la asociación de PHPDoc del método
   `estudiante()` y, en cascada, hizo que Larastan dejara de reconocer
   `$model->id` en 6 Policies completamente ajenas (Certificados, Pagos,
   Incidencias, Evaluaciones, CursoVirtual). Detectado por Larastan antes
   de subir nada.
2. **`mount(?string $codigo = null)` no funcionaba** (QR): Livewire/Volt
   solo inyecta en `mount()` los parámetros de RUTA, no la query string.
   Detectado con una prueba end-to-end real (`curl` contra el servidor
   vivo), no solo el test unitario. Corregido con `Request::query('codigo')`.
3. **`Apoderado` sin `estudiante_id` en su docblock `@property`**
   (Búsqueda global): el campo sí existía en `$fillable` y en la base de
   datos, pero Larastan lo marcó como propiedad indefinida en cuanto el
   código nuevo lo usó fuera del propio módulo Matrícula.
4. **`Ejemplar` sin `protected $table`** (Biblioteca): Eloquent adivinó el
   nombre de tabla en inglés (`ejemplars`) en vez del real en español
   (`ejemplares`) — toda operación fallaba con "no such table", atrapado
   de inmediato por el test suite antes de llegar a probarse en vivo.

---

## 11. Riesgos pendientes

- **Sin canal de comunicados institucionales por email**: el correo hoy
  solo sirve para autenticación (reset de contraseña), no para
  comunicados masivos — la sección 18 lo describe como capacidad futura,
  no se ha empezado.
- **Sin API pública expuesta**: la sección 30 (integraciones) no tiene una
  capa explícita de API; la única integración externa real (SIAGIE) es de
  clasificación de datos, no de sistema en vivo.
- **Informe/matriz de trazabilidad no eran un proceso continuo**: se
  generaron una vez, al cierre — para que sigan siendo útiles, hay que
  actualizarlos manualmente cada vez que se agregue o cambie un módulo.

---

## 12. Evaluación ISO/IEC 25010

| Característica | Estado | Nota |
|---|---|---|
| Adecuación funcional | Alta | Los 6 puntos que la auditoría marcó como faltantes por completo, más asistencia docente y la búsqueda avanzada multi-filtro, ya están implementados y probados. |
| Eficiencia de desempeño | Alta | Fase 11 (sección 3.2) revisó los 7 módulos nuevos uno por uno: 2 N+1 corregidos, 2 índices agregados, 3 listas paginadas -- cada hallazgo con su propio test de regresión, no solo corregido "a ojo". |
| Compatibilidad | Media | Sin API pública; SIAGIE sigue siendo integración de datos, no de sistema en vivo. |
| Usabilidad | Alta | Los 7 módulos nuevos reutilizan el mismo sistema de diseño (tokens de color, `x-badge`, `x-select-input`, etc.) sin introducir un patrón visual nuevo. |
| Fiabilidad | Alta | Suite completo en verde (ver sección 9 para la cifra final), 4 bugs reales atrapados antes de producción, ninguno llegó a estar en vivo sin corregir. |
| Seguridad | Alta | Autorización server-side probada explícitamente por rol en cada módulo nuevo, no solo ocultando botones. |
| Mantenibilidad | Alta | Pint y Larastan limpios en cada entrega; convención de comentarios explicando decisiones no obvias, no qué hace el código. |
| Portabilidad | Alta | Responsive heredado del sistema de diseño existente; no se auditó módulo por módulo de forma exhaustiva. |

---

## 13. Recomendaciones futuras

1. Automatizar la actualización de este informe y la matriz de
   trazabilidad (por ejemplo, generarlos desde `docs/BITACORA.md` en vez
   de mantenerlos a mano) para que no queden desactualizados con el
   próximo módulo que se agregue.
2. Si en algún momento aparece lógica de cliente genuinamente compleja
   (un calendario interactivo más rico, un editor), reevaluar la decisión
   de TypeScript de la sección 38 — hoy no se justifica.
3. Definir si el canal de comunicados institucionales por email (sección
   18) es una prioridad real antes de construirlo.
4. `FiltroMatriculaAcademico::filtrarMatriculas()` aún calcula
   "¿tiene paralelos?" con un `count()` propio por llamada (ver
   `app/Modules/Academico/Support/FiltroMatriculaAcademico.php`) -- barato
   hoy por el volumen de datos, pero cacheable si el catálogo de cursos
   crece mucho.

---

## 14. Matriz de trazabilidad

Ver `docs/MATRIZ_TRAZABILIDAD.md` — se mantiene como documento aparte por
su tamaño (20 filas, una por cada agrupación de requerimientos del prompt
maestro, con su ruta, service, modelo/tabla y test reales).
