# Bitácora de cambios

Registro cronológico (más reciente arriba) de los cambios importantes hechos
al sistema, con el detalle suficiente para entender el porqué sin tener que
releer todo el historial de Git. Cada entrada nueva se agrega arriba, con
fecha y los commits que le corresponden.

---

## 2026-09-15 (noche, cont. 8)

### Informe final, matriz de trazabilidad, fase 11 (optimización) y cierre de la búsqueda avanzada

El usuario preguntó explícitamente si se habían seguido todos los pasos
del prompt maestro. La respuesta honesta: la metodología sí (analizar
antes de modificar, no inventar datos, probar todo, autorizar en
backend), pero varios entregables *procesales* que el documento exige
como cierre formal (informe final §56, matriz de trazabilidad §51, una
pasada de optimización §49/fase 11, y la búsqueda avanzada multi-filtro
completa de §25, que se había dejado como buscador global simple) no se
habían hecho. Se hicieron los 4 hoy.

- **`docs/INFORME_FINAL.md`**: las 14 secciones que pide §56 (resumen
  ejecutivo, arquitectura, módulos, requerimientos cumplidos/pendientes,
  cambios en BD/frontend/backend, seguridad, pruebas, bugs encontrados,
  riesgos, ISO/IEC 25010, recomendaciones, matriz).
- **`docs/MATRIZ_TRAZABILIDAD.md`**: 20 filas (una por agrupación de
  requerimientos del prompt maestro), con ruta/service/modelo/tabla/test
  reales -- verificados con `php artisan route:list` y `ls tests/`, no
  de memoria.
- **Fase 11 (optimización)**: una auditoría real (agente de exploración)
  sobre los 7 módulos de hoy encontró 2 N+1 reales (adjuntos de Trámites
  sin eager-load de `media`; docente de Calendario sin eager-load en
  `EvaluacionService::horariosDelDocente()`, que también usa Asistencia),
  2 índices faltantes (`categoria` en Trámites, `fecha` en Asistencia
  docentes -- ninguno de los dos servía al índice compuesto que ya
  existía, por la regla del prefijo izquierdo) y 3 listas sin paginar que
  crecen sin límite (`Tramites::todos()`, `Biblioteca::catalogo()`,
  `Biblioteca::prestamosActivos()`). Los 5 hallazgos se corrigieron, cada
  uno con su propio test de regresión (conteo de queries con
  `DB::enableQueryLog()` para los N+1, `total()`/`lastPage()` para la
  paginación).
- **Búsqueda avanzada (§25) completa**: se extrajo el filtro en cascada
  ciclo→grado→curso (con la regla de "paralelos") de
  `ReporteService`, donde vivía duplicado en 5 de sus 7 métodos como
  métodos privados, a `App\Modules\Academico\Support\FiltroMatriculaAcademico`
  (clase con métodos estáticos, sin estado) -- se reutiliza desde
  Reportes (sin cambiar su comportamiento, verificado con su suite
  completo) y desde el nuevo bloque "Búsqueda avanzada" de
  `matricula.index`, que agrega un cuarto filtro (docente) que Reportes
  no tenía.
  - **Bug real encontrado y corregido antes de escribir el código**: el
    primer diseño exigía la comprobación estricta de "paralelos"
    (`whereHas` contra IDs de horario concretos) también cuando solo se
    filtraba por docente sin curso. Eso habría excluido por error a
    estudiantes que sí llevan la materia de ese docente pero nunca
    necesitaron una asignación explícita en `matricula_horario` (por no
    tener paralelos) -- se corrigió para que el filtro por docente sin
    curso se comporte como el de franja (acota por grado+ciclo, sin
    exigir la asignación explícita), y quedó cubierto por un test
    dedicado (`FiltroMatriculaAcademicoTest`) antes de integrarlo.
- Verificado en vivo contra la base de datos real de desarrollo vía
  tinker: filtrar por grado real reduce de 55 a 16 estudiantes; filtrar
  por un docente que dicta en 4 grados distintos del mismo ciclo trae 50
  de 55 -- resultado correcto, no un bug, confirmado revisando cuántas
  combinaciones grado+ciclo dicta ese docente en los datos de prueba.
- Suite completo: **1035 → 1049** (+14), Pint y Larastan limpios.

---

## 2026-09-15 (noche, cont. 7)

### Se quitó también la mascota astronauta

Pendiente desde la sesión anterior: en su momento se quitó la mascota oso
(login + saludo del dashboard) pero se dejó el astronauta SVG sin tocar
hasta que el usuario confirmara. Hoy pidió quitarlo también.

- Vivía en dos lugares: el panel de bienvenida a la izquierda del login/
  2FA/recuperar contraseña (`layouts/guest.blade.php`) y un widget
  decorativo al pie del sidebar del panel (confeti + "¡Vamos, Excelencia
  360! 🚀", en `sidebar-nav.blade.php`). Se quitó de ambos; en el login se
  conservó el degradado, el resplandor y el texto de bienvenida (mismo
  criterio que ya tenía el login principal, que tampoco lleva mascota
  desde antes). En el sidebar, el bloque completo del widget se eliminó
  (no tenía sentido dejar el confeti sin nada adentro) y el `mt-auto` que
  lo empujaba al fondo pasó al bloque de "Mi perfil" que sigue.
- Como ya no queda ningún uso, se borró
  `components/dashboard/mascot.blade.php` y el CSS que solo existía para
  ella (`.sidebar-mascot`, `.confetti-piece`, `.mascot-float` y sus
  `@keyframes`) en `resources/css/app.css`. Comentarios que la mencionaban
  en código que sigue vigente (`.dashboard-hero-gradient`, `.hero-glow`,
  el token `--gradient-hero-*`) se actualizaron para no describir algo
  que ya no está.
- Verificado: suite completo sigue en 1035/1035, Pint y Larastan limpios;
  `/forgot-password` (usa el layout tocado) responde 200 y su HTML ya no
  contiene ninguna referencia a la mascota.

---

## 2026-09-15 (noche, cont. 6)

### Biblioteca (séptimo y último módulo del ERP)

Módulo nuevo y completo (`app/Modules/Biblioteca`): catálogo de libros,
ejemplares físicos con código de inventario, y circulación (préstamos y
devoluciones). Era el único punto de la auditoría sin nada reutilizable
-- no existía ninguna tabla ni concepto parecido en el sistema.

- **Modelos**: `Libro` (título, autor, ISBN, categoría, editorial, año) →
  `Ejemplar` (un código de inventario único por copia física, con estado
  disponible/prestado/perdido/en_reparación) → `Prestamo` (quién se lo
  llevó, quién se lo entregó, fecha esperada de devolución, fecha real,
  estado). `estaVencido()` se calcula al vuelo comparando la fecha
  esperada contra hoy -- no hay un job ni un estado "vencido" guardado
  aparte que alguien tenga que mantener sincronizado.
- **Solo Docente y Estudiante piden prestado** (tienen cuenta de acceso
  con DNI para identificarlos en el mostrador): mismo criterio ya usado
  en Asistencia de docentes para excluir a Personal, que no tiene
  `user_id` en el modelo actual.
- Una página para bibliotecario/staff (`biblioteca.index`: catálogo +
  agregar libros/ejemplares + préstamos activos con devolver/marcar
  perdido, todo detrás de `biblioteca.gestionar`) y otra de autoservicio
  (`biblioteca.mis-prestamos`, `biblioteca.ver_propio`) para que
  cualquier estudiante o docente vea su propio historial. El catálogo en
  sí (`biblioteca.ver`) es visible para todos los roles salvo Apoderado,
  que no tiene relación directa con préstamos de libros.
- **Bug real encontrado en el camino**: el modelo `Ejemplar` no declaraba
  `protected $table`, así que Eloquent adivinaba el nombre de tabla en
  inglés (`ejemplars`, con "s" simple) en vez del nombre real de la
  migración (`ejemplares`, plural correcto en español) -- toda operación
  sobre ejemplares fallaba con "no such table". Detectado de inmediato
  por el propio suite de tests (no llegó a probarse en vivo sin antes
  corregirlo); se agregó la propiedad explícita.
- Tests: `BibliotecaServiceTest` (11) y `BibliotecaPermisosTest` (8
  métodos, 13 casos contando `@dataProvider`) -- 24 casos nuevos, todos
  pasando. Verificado en vivo contra la base de datos real de desarrollo
  vía tinker: registrar libro → agregar ejemplar → prestar → devolver,
  con el ejemplar cambiando de estado correctamente en cada paso.

Con esto se cierran los 7 puntos que la auditoría del prompt maestro
había marcado como faltantes por completo o parciales: Apoderados, QR,
Trámites, Calendario, Asistencia docente, Búsqueda global y Biblioteca.

---

## 2026-09-15 (noche, cont. 5)

### Búsqueda global (sexto punto del ERP)

Caja de búsqueda en el topbar (`livewire.busqueda.buscador-global`,
embebida en `layout.navigation`) que busca a la vez entre Estudiantes,
Apoderados, Docentes y Personal -- antes cada uno solo se podía buscar
entrando a su propio módulo.

- **No es un módulo con tabla propia**: `BusquedaGlobalService`
  (`app/Modules/Busqueda`) no tiene modelo ni migración -- son las mismas
  consultas `LIKE` que ya usa cada índice (Matrícula, Docentes, Personal),
  unidas en un solo resultado. Sin Service Provider tampoco: no hay rutas
  ni migraciones que registrar, y el componente Livewire se descubre solo
  por convención de carpetas, igual que cualquier otro Volt.
- **Reutiliza los permisos que ya existían** (`matricula.ver`,
  `docentes.ver`, `personal.ver`) en vez de inventar un `busqueda.*`
  nuevo: cada tipo de resultado solo aparece si el usuario ya podía verlo
  por su cuenta.
- **Enlaces reales, no una promesa de "página en construcción"**:
  Estudiante y Apoderado (este último resuelve a la ficha de su hijo) ya
  tenían una ruta propia (`matricula.show`) y enlazan directo ahí. Docente
  y Personal no tienen ficha propia en el sistema actual (se editan desde
  un modal en su índice, no una URL) -- en vez de inventar rutas nuevas
  que nada más usa el buscador, el resultado enlaza al índice existente
  con `?q=<dni>`, y se le agregó a `docentes.index`/`personal.index` (dos
  líneas cada uno) leer ese parámetro para prefiltrar su propia lista al
  cargar. Mismo patrón ya aprendido en Verificación por QR:
  `Request::query()`, porque Livewire/Volt no inyecta la query string en
  `mount()`.
- **Bug real encontrado en el camino**: `Apoderado` no tenía
  `estudiante_id` en su docblock `@property` (aunque sí está en
  `$fillable` y en la base de datos) -- Larastan lo marcó como propiedad
  indefinida en cuanto el nuevo código la usó. Se corrigió agregando la
  anotación que faltaba, sin tocar nada más del modelo.
- Tests: `BusquedaGlobalServiceTest` (9) y `BuscadorGlobalTest` (6,
  incluye los dos índices con `?q=`) -- 15 casos nuevos, todos pasando.
  Verificado en vivo contra la base de datos real de desarrollo vía
  tinker.

---

## 2026-09-15 (noche, cont. 4)

### Asistencia de docentes (quinto módulo del ERP)

Módulo nuevo (`app/Modules/AsistenciaDocentes`) para el control de
asistencia laboral del personal docente -- distinto del módulo
`Asistencia` ya existente, que registra si un **estudiante** fue a una
**sesión de clase** puntual. Este nuevo módulo registra si un **docente**
se presentó a trabajar en un **día calendario**, con Coordinador/
Administrativo/Dirección como quienes marcan el día de todos y el propio
docente viendo (no editando) su historial.

- **Alcance deliberadamente acotado a Docente, no a Personal**: se
  verificó primero que `Personal` (portería, limpieza, psicología...) no
  tiene `user_id` ni ningún campo de horario laboral -- por diseño no
  inicia sesión en el sistema. Extender asistencia a Personal exigiría
  inventar datos que hoy no existen (a quién atribuirle el registro, con
  qué horario compararlo), así que se dejó fuera en vez de improvisar esa
  regla de negocio.
- **Reutiliza el vocabulario de estados que ya existía**
  (`App\Modules\Asistencia\Enums\EstadoAsistenciaEnum`:
  presente/tardanza/falta/justificado) en vez de duplicarlo -- significa
  exactamente lo mismo para un docente que para un estudiante.
  `AsistenciaDocente` es un registro por (docente, fecha), no por sesión:
  la asistencia laboral es diaria, no por curso.
- A diferencia del flujo de estudiantes (que separa "solicitud de
  justificación" del estudiante y su aprobación posterior), aquí quien
  registra la asistencia (staff) ya tiene la autoridad para marcar
  directamente "justificado" con una nota/archivo adjunto -- no hace
  falta el mismo circuito de solicitud/aprobación que existe para que el
  estudiante inicie el trámite.
- Se agregó `User::docente(): HasOne` (no existía; ya existían
  `estudiante()` y `apoderados()`) para que la vista de "mi historial" del
  docente resuelva su propia ficha sin pasar por el módulo Docentes.
- Nuevo enlace "Asistencia docente" en el menú lateral, justo debajo de
  "Asistencia" (estudiantes), visible para Dirección/Coordinador/
  Administrativo/Docente.
- Tests: `AsistenciaDocenteServiceTest` (7) y
  `AsistenciaDocentesPermisosTest` (7 métodos, 11 casos contando los
  `@dataProvider`) -- 18 casos nuevos, todos pasando. Verificado en vivo
  contra la base de datos real de desarrollo vía tinker: registrar
  tardanza con observación → aparece en el resumen del docente → permisos
  correctos por rol.

---

## 2026-09-15 (noche, cont. 3)

### Calendario académico (cuarto módulo del ERP)

Módulo nuevo (`app/Modules/Calendario`) que unifica, en una sola vista
mensual, tres orígenes que antes vivían separados: clases recurrentes,
evaluaciones con fecha propia y eventos institucionales puntuales.

- **No se tocó nada existente.** `CalendarioService` reutiliza
  `EvaluacionService::todos()/horariosDelDocente()/horariosDelEstudiante()`
  (ya usados por "Mis evaluaciones"/"Mi libreta") para decidir qué
  horarios corresponden a cada usuario según su rol, en vez de duplicar
  esa lógica. Las clases se materializan expandiendo cada
  `Horario`/`HorarioDia` (recurrente, sin fecha propia) día por día dentro
  del mes consultado, acotadas al rango real del `Ciclo`
  (`fecha_inicio`/`fecha_fin`) al que pertenece el horario -- una clase no
  aparece antes de que empiece o después de que termine su ciclo, aunque
  el día de la semana coincida.
- **Alcance por rol** (mismo criterio que ya usa "Mis evaluaciones"):
  Dirección/Coordinador (con `academico.ver`) ven todas las
  clases/evaluaciones; un Docente solo las de sus propios horarios; un
  Estudiante o Apoderado solo las de su grado/ciclo (o el de sus hijos), y
  únicamente evaluaciones ya publicadas -- igual que en su portal.
  Administrativo/Tesorería no tienen alcance académico, así que solo ven
  los eventos institucionales (abajo), no clases ni evaluaciones.
- **Eventos puntuales** (`EventoCalendario`: reunión, acto institucional,
  feriado/no lectivo, otro) son nuevos en este módulo -- ninguna tabla
  existente los cubría (`Vacaciones` resultó ser licencia individual por
  estudiante, no feriados institucionales; ver auditoría). Visibles para
  todos los roles; crear/editar/eliminar requiere `calendario.gestionar`
  (Coordinador, Administrativo, Dirección, igual que en Trámites). Un
  evento de varios días (p. ej. una semana cultural) se expande en un
  ítem por cada día dentro del mes consultado.
- Vista Livewire de calendario mensual con navegación (mes anterior/
  siguiente/hoy), grilla de 7×N días con hasta 2 ítems visibles por
  celda, y un panel de detalle al seleccionar un día.
- Nuevo enlace "Calendario" en el menú lateral, visible para cualquier
  rol con `calendario.ver` (todos).
- Tests: `CalendarioServiceTest` (13, incluyendo expansión de eventos
  multi-día, límites del ciclo, y alcance por cada rol) y
  `CalendarioPermisosTest` (8 métodos, 17 casos contando los
  `@dataProvider` por rol) -- 30 casos nuevos, todos pasando; **suite
  completo: 978/978**. Verificado en vivo contra la base de datos real de
  desarrollo vía tinker: crear un evento → aparece en `itemsDelMes()` del
  mes actual junto con las clases/evaluaciones reales ya sembradas →
  permisos correctos por rol.

---

## 2026-09-15 (noche, cont. 2)

### Trámites / FUT (tercer módulo del ERP)

Módulo nuevo (`app/Modules/Tramites`) para solicitudes administrativas de
cualquier usuario (constancias, reclamos, pedidos económicos, etc.), con
seguimiento de estado y resolución — lo que el prompt maestro llama FUT
(Formulario Único de Trámite).

- **Modelo `SolicitudTramite`** (`solicitudes_tramite`): solicitante,
  categoría (académico/administrativo/económico/otro), asunto,
  descripción, adjuntos (Spatie MediaLibrary), estado (registrada →
  en_revisión/observada → aprobada/denegada/atendida, o archivada),
  responsable y resolución. Todos los roles pueden crear y ver sus
  propios trámites (`tramites.crear`, `tramites.ver_propio`); Coordinador
  y Administrativo además los gestionan todos (`tramites.gestionar`):
  cambiar estado, dejar resolución, filtrar por estado/categoría.
- Pasar a un estado que exige explicación (observada, aprobada, denegada,
  atendida) sin escribir resolución lanza un error de validación —
  `EstadoTramiteEnum::requiereResolucion()`. Al llegar a un estado final
  (aprobada, denegada, atendida) se registra `atendido_en` y se notifica
  al solicitante (`TipoNotificacionEnum::TRAMITE_ATENDIDO`), reutilizando
  `NotificacionService` ya existente — nada de canales nuevos.
- Una sola página Livewire (`tramites.index`), igual que Incidencias:
  el mismo componente muestra la vista "mis trámites" o la vista de
  gestión según el permiso del usuario autenticado, revalidado en cada
  acción (`abort_unless(...->hasPermissionTo(...), 403)`), no solo
  ocultado en la interfaz.
- Nuevo enlace "Trámites" en el menú lateral, visible para cualquier rol.
- Tests: `TramiteServiceTest` (8, reglas de negocio del servicio) y
  `TramitesPermisosTest` (11, acceso por rol, aislamiento "ver_propio",
  restricción de "gestionar" a Coordinador/Administrativo/Dirección,
  validación de resolución obligatoria) — 24 en total, todos pasando.
- Verificado en vivo contra la base de datos real (`tinker`): registrar →
  cambiar a "atendida" con resolución → notificación creada → permisos
  correctos por rol, con limpieza de los datos de prueba al final.

---

## 2026-09-15 (noche, cont.)

### Verificación de documentos por QR (segundo módulo del ERP)

Ya existía una página pública de verificación (`/verificar-certificado`)
donde el interesado escribía a mano el código impreso en el certificado.
Se le agregó un QR que lleva directo al resultado.

- `App\Shared\Support\QrCode`: genera el QR como PNG embebible (data URI)
  dibujando a mano con GD la matriz de módulos de `bacon/bacon-qr-code`
  (ya era dependencia, solo se usaba para el QR del 2FA) — no sus
  renderers de SVG/Imagick, porque DomPDF no soporta SVG de forma
  confiable y este servidor no tiene Imagick instalado (`php -m`), solo
  GD. Verificado end-to-end con un lector independiente (`pyzbar`) sobre
  un PDF real generado por el sistema: decodifica exactamente la URL
  esperada.
- `Certificado::urlVerificacion()`: la URL de verificación con el código
  ya incluido (`?codigo=...`).
- `certificado.blade.php` (la plantilla PDF que ya comparten los 6 tipos
  de documento del módulo: certificado de estudios y las 5 constancias)
  imprime el QR junto al código de texto — ambos caminos siguen
  disponibles, ninguno reemplaza al otro. La libreta de notas usa una
  plantilla PDF aparte (`pdf.libreta`, sin código de verificación) y
  queda fuera de este cambio: no es parte del sistema de verificación
  existente.
- `verificar.blade.php`: si la URL trae `?codigo=...`, se autocompleta y
  verifica de una vez, sin tocar el botón.
- **Bug real encontrado en el camino:** `mount(?string $codigo = null)`
  no funcionaba — Livewire/Volt solo inyecta en `mount()` los parámetros
  de RUTA (segmentos tipo `{estudiante}`), no la query string. Se
  detectó con una prueba end-to-end real (`curl` contra el servidor
  vivo, no solo el test unitario) antes de darlo por bueno; la lectura
  correcta es `Request::query('codigo')`.
- Otro ajuste en el camino: el primer test que escribí para "el PDF
  incluye el QR" comparaba el data URI contra los bytes ya compilados
  del PDF -- DomPDF los comprime y los reincrusta como XObject binario,
  así que la cadena literal nunca aparece ahí. Se corrigió comparando
  contra el HTML de la vista (lo que DomPDF recibe antes de compilar).
- Verificado en vivo: PDF real generado, rasterizado y decodificado con
  un lector de QR independiente (contenido exacto); página de
  verificación probada escaneando (simulado) y escribiendo a mano.

---

## 2026-09-15 (noche)

### Portal de Apoderados (primer módulo del prompt "Excelencia 360 ERP")

El usuario entregó un prompt de 57 secciones pidiendo adaptar el sistema a
un ERP académico completo (ver auditoría publicada como Artifact esa misma
tarde). Se auditó primero contra el código real sin tocar nada; el usuario
priorizó **Apoderados: rol y portal** como primer módulo a construir, y
**mantener Livewire sin TypeScript** para el requisito de §38.

- **Cuenta propia para cada apoderado:** `Apoderado` (dato que ya existía,
  vinculado 1:1 a cada estudiante) ahora puede tener una cuenta de acceso
  (`apoderados.user_id`), creada/reutilizada con el mismo criterio que
  estudiantes y docentes: correo `{dni}@ceba.test`, contraseña inicial =
  DNI. Como una misma persona puede tener más de un hijo matriculado (una
  fila de `Apoderado` por hijo, sin un DNI único en la tabla), varias filas
  pueden compartir el mismo `user_id` — ve a todos sus hijos desde un solo
  inicio de sesión. Se integró directamente en
  `MatriculaService::registrarApoderado()`: todo apoderado nuevo (por el
  wizard o por carga masiva) recibe acceso automáticamente, sin paso
  manual aparte. Comando `apoderados:generar-accesos` (idempotente) para
  los 21 que ya existían antes de este cambio — ya corrido en local.
- **Rol nuevo:** `apoderado`, con un único permiso de solo lectura
  (`matricula.ver_propio_hijo`) sobre el/los hijo(s) vinculados a su
  cuenta. Nueva puerta de entrada "Apoderado" en el selector del login
  (antes solo "Personal administrativo" / "Estudiante").
- **Pantalla `/matricula/mis-hijos`:** reutiliza el mismo resumen de solo
  lectura (grados cursados, situación de pagos, documentos, notas) que ya
  usa Coordinación/Dirección en "Historial de estudiante" — se extrajo esa
  parte a un componente compartido (`x-historial-estudiante.resumen`) para
  no duplicar ~250 líneas de marcado entre ambas pantallas. Si el
  apoderado tiene más de un hijo, elige a cuál ver; si tiene uno solo, se
  muestra directo. Incluye exportar a PDF.
- **Seguridad:** `estudianteSeleccionadoId` es una propiedad pública de
  Livewire (el cliente puede intentar modificarla directamente, no solo a
  través del selector) — cada punto que la usa vuelve a validar contra los
  hijos reales de esa cuenta en vez de confiar en el valor recibido;
  cubierto por test (`manipular la propiedad publica directamente no
  filtra al hijo de otro`).
- **Bug real encontrado y corregido en el camino:** al agregar la relación
  `User::apoderados()`, un doc-comment quedó huérfano (dos bloques
  `/** */` seguidos, sin código entre medio) — eso rompió la asociación de
  PHPDoc de `estudiante()` y, en cascada, hizo que Larastan dejara de
  reconocer `$user->id`/`$model->id` en 6 Policies completamente ajenas
  (CursoVirtual, Certificado, Evaluaciones, Incidencias, Pagos). Detectado
  por Larastan antes de subir nada; correspondencia 1 docblock ↔ 1 método
  restaurada.
- Verificado en vivo: login real como apoderado (DNI de producción/demo),
  "Mis hijos" muestra los datos reales del hijo (matrícula, cuotas
  pendientes, pagos). 899 → +14 tests nuevos, todos en verde. Pint y
  Larastan limpios.

---

## 2026-09-15 (tarde)

### Se quitó el violeta heredado de CEBA: paleta del panel a turquesa

`--color-accent` (el primario FUNCIONAL de toda la interfaz del panel —
botones, links activos, foco) era violeta, heredado del rediseño de CEBA.
Se recoloreó a turquesa/cian para que no desentone con la identidad de
Excelencia 360, en `resources/css/app.css`:

- `--color-accent`/`--color-accent-soft` (claro y oscuro) y
  `--gradient-hero-*` (degradado del saludo del Dashboard y resplandor
  ambiental del login/2FA): de violeta a turquesa → azul.
- Claro y oscuro usan tonos *distintos* a propósito: ningún turquesa único
  funciona a la vez como fondo de botón con texto blanco Y como texto
  legible sobre un fondo casi negro (el violeta anterior tenía la misma
  limitación). Los nuevos valores se verificaron con la misma fórmula de
  contraste WCAG usada para la web pública.
- La insignia/mochila/guante de la mascota astronauta (`dashboard/mascot.blade.php`)
  y las partículas del fondo del login (`vortex-dust.js`) estaban en
  violeta a propósito para combinar con el accent anterior — se pasaron a
  `rgb(var(--color-accent))` (la mascota, así sigue la paleta activa sola)
  y a un cian claro (las partículas).

### Banner del Dashboard: sin espacio vacío arriba

`hero-banner.blade.php` tenía `mt-8 sm:mt-10` para dejarle aire a la
mascota que asomaba por encima del banner — ya no está (ver entrada de
ayer). Se quitó ese margen: el padding de `<main>` alcanza, igual que en
el resto de páginas del panel.

### Login: video de fondo, y logo sin caja blanca

- Video de fondo del login reemplazado por el que pasó el usuario.
- El logo del login/2FA ya no lleva una tarjeta blanca detrás
  (`bg-white/95 rounded-xl px-4 py-2`): el PNG real ya es transparente
  (se verificó al conectarlo, ver entrada de ayer), así que sobra —
  ahora flota directo sobre el video con un `drop-shadow` para que
  se lea bien encima de cualquier escena.

---

## 2026-09-15

### Logo oficial de la institución

La institución entregó el logo (emblema con gorro de graduación, rayos y
libro, con el wordmark "EXCELENCIA 360" ya incluido debajo, en un solo
archivo vertical). Quedaba pendiente desde el día anterior; ya está
conectado en todo el sistema vía `config/institucion.php` →
`App\Shared\Support\Institucion`, sin tocar el archivo entregado (mismos
colores/proporciones/isotipo):

- `public/images/excelencia360/logo.png`: archivo completo tal cual,
  usado en los pocos lugares con espacio vertical de sobra para lucirlo
  entero (login, panel de 2FA/recuperar contraseña) — `x-brand-logo
  variant="mark"`.
- El navbar, el footer y el sidebar del panel necesitaban algo más
  horizontal: el archivo es vertical (950×1510) y a la altura de un
  navbar su wordmark queda ilegible. Se recorta solo el emblema (el 78%
  superior, por CSS `object-fit: cover` + `object-position: top`, sin
  tocar el PNG) y se combina con el nombre escrito aparte —
  `x-brand-logo variant="icon"` (sidebar) y `variant="full"` (navbar/footer,
  el nuevo valor por defecto del componente).
- Los PDFs (certificados, libretas, recibos) usan un recorte físico
  aparte, `logo-emblema.png` (generado una vez con GD, mismo criterio de
  corte), porque DomPDF no soporta el recorte por CSS que sí usa la web.
- El favicon pasa a usar ese mismo emblema recortado en vez del marcador
  provisional (se ve mejor a 16-32px sin el wordmark encima).

Se quitaron `public/images/logo.png` (provisional, en la raíz de
`images/`) y `LEEME.md`, ya sin objeto.

### Se quitó la mascota (oso) del login y del dashboard

Video del oso en el login y su imagen en el saludo del dashboard,
eliminados junto con los 3 archivos que solo ellos usaban (`pet.png`,
`pet-video.mp4`, `pet-video-transparent.webm`). La otra mascota del
sistema (el astronauta ilustrado en SVG, del panel de 2FA/recuperar
contraseña y del pie del sidebar) no se tocó.

---

## 2026-09-14

Nace **Excelencia 360** como copia del sistema CEBA, con base de datos,
usuario MySQL (`excelencia360`, sin acceso a `ceba`), puerto (8360) y cookie
de sesión propios. Esta entrada cubre la adaptación de la identidad
institucional y la web pública a **GRUPO EXCELENCIA 360**.

### 1. Identidad institucional centralizada

- `config/institucion.php` es la única fuente de nombre, RUC, dirección,
  gerente, misión, visión, valores, cursos, servicios y canales de contacto.
  `App\Shared\Support\Institucion` la expone a vistas y PDFs y resuelve el
  logo.
- **Logo pendiente:** la institución aún no entregó el archivo. Basta copiarlo
  a `public/images/excelencia360/logo.png` (ver `LEEME.md` en esa carpeta)
  y aparece en navbar, footer, login, panel, PDFs y favicon. Mientras tanto
  se muestra la marca tipográfica "EXCELENCIA 360" (`<x-brand-logo>`) y el
  favicon provisional `favicon.svg`.
- Se eliminaron los escudos de CEBA (`Logo.png`, `logo-ca.png`) y el fondo
  del hero anterior (`fondo-pri.gif`).

### 2. Web pública nueva (módulo Landing)

- Tema claro con la paleta oficial: tokens `--e360-*` en `app.css` y
  colores `e360.*` en Tailwind. Los botones con texto blanco usan los tonos
  "deep" (`#0F7A78`, `#C8461C`) porque el turquesa y el naranja del logo no
  llegan al contraste WCAG AA; el color original queda en formas, íconos y
  fondos suaves. Tipografía: Plus Jakarta Sans (títulos) + Inter (texto).
- Secciones: hero (con el arco 360 como firma visual), propuesta de valor,
  Conócenos (descripción + ficha institucional), misión y visión, valores,
  cursos, servicios, blog (estado vacío hasta tener publicaciones reales) y
  contacto. Componentes en `components/landing/` (navbar, footer, cards…).
- Página de detalle por curso: `/cursos/{slug}` (`landing.curso`).
- El formulario de contacto ahora pide **Asunto** (curso, servicio o
  consulta general) en vez de "programa de interés": columna renombrada
  (`asunto`), validación cerrada a esas opciones, estados de carga/éxito/
  error y preselección del asunto por `?asunto=` o al hacer clic en "Más
  información" de un curso/servicio.
- Se retiró el botón flotante de WhatsApp: no hay número institucional.

### 3. Login, panel y PDFs

- `APP_NAME`, layouts de login/guest/panel, sidebar, dashboard y los
  encabezados de todos los PDFs usan `config('institucion.*')`.
- Certificados y libretas: se quitaron los datos legales de CEBA (código
  modular, UGEL 05, correo, firma del director). Ahora firman con el Gerente
  General y el domicilio/RUC de la institución (editable en config). Las
  plantillas ya guardadas con los textos por defecto de CEBA se actualizaron
  por migración; las personalizadas no se tocan.
- Recibos: una sola marca institucional; desaparecen los centros de costo
  PB/CA (`SerieReciboEnum::centroCostoIniciales()` y `logoPath()`).

### Pendientes que dependen de la institución

- Logo oficial (ver arriba). Teléfono, correo y redes sociales (`config/institucion.php`).
- Duración, precio, certificación, docente y horario de cada curso.
- La visión dice "Al 208 ser reconocidos…" tal cual fue entregada; confirmar
  el año.
- Las cuentas autogeneradas siguen usando el dominio `{dni}@ceba.test`
  (`App\Shared\ValueObjects\Dni`): cambiarlo afecta a los usuarios ya
  existentes, así que se dejó para una decisión aparte. La mascota y el
  video de fondo del login también son los heredados.

---

## 2026-09-07

Sesión larga con varios frentes: seguimiento de sesiones compartidas,
exportación de reportes, rediseño de comprobantes, nuevas cuentas de
Dirección, y dos módulos nuevos de RR.HH. (Docentes, Contratos, Personal).

### 1. Nombre de quien ingresa en cuentas compartidas + sesión más larga

**Commit:** `22d147d`

Varias cuentas institucionales (sobre todo el rol Dirección) las usa más de
una persona física. Antes, "Sesiones activas" en Mi Perfil solo mostraba la
IP, lo que no servía para saber quién entró con esa cuenta compartida.

- Al iniciar sesión ahora se pide "¿Con qué nombre ingresas?" (campo nuevo,
  obligatorio, antes del correo).
- Ese nombre se guarda por sesión en la tabla nueva `registros_ingreso`
  (vinculada al `id` real de la sesión de Laravel), tanto en el login
  directo como en el que pasa por el reto de 2FA (el nombre se guarda
  temporalmente en la sesión mientras se resuelve el 2FA).
- "Sesiones activas" ahora muestra el nombre de quien ingresó en cada
  sesión (la IP pasó a texto secundario), y se agregó una sección "Horas
  registradas por nombre" con el total de horas acumuladas por cada persona
  que usó esa cuenta.
- El registro de una sesión se cierra (`finalizado_en`) al hacer logout, al
  revocar sesiones desde el propio Perfil, o cuando `VerificarCuentaActiva`
  fuerza el cierre de una cuenta desactivada.
- `SESSION_LIFETIME` subió de 120 a 480 minutos para que la sesión no
  expire tan rápido.

**Detalle técnico importante:** en el camino se encontró un bug real —
`VerificarCuentaActiva` (middleware global) no puede recibir servicios como
parámetro extra de `handle()`, porque el `Pipeline` de Laravel invoca los
middlewares globales llamando directo a `handle($request, $next)`, sin
pasar por el resolver de dependencias del contenedor. `SessionControlService`
se inyecta por constructor en su lugar.

### 2. Exportar a PDF desde Flujo de Caja

**Commit:** `4d67c01`

Botón "Exportar PDF" en `/flujo-caja` con el resumen del mes (ingresos,
egresos, saldo neto) y el detalle de movimientos.

### 3. Rediseño del recibo de pago (formato físico)

**Commit:** `2f6bd9f`

El PDF del recibo se rehízo para calzar con el formato de papel que usa el
colegio: casillas de día/mes/año para fecha de emisión y de pago, datos del
alumno, tabla de conceptos, cuadro de observación, y pie con
cuota/grupo/medio de pago. (Este formato se volvió a tocar más abajo, ver
punto 8, para sumar las dos series.)

### 4. Nuevas cuentas de Dirección: Diana, Aaron, Ruth y Reyna

**Commit:** `17658bf`

`ProduccionSeeder` pasó de crear una sola cuenta de Dirección (Walter,
hardcodeada) a recorrer un arreglo `CUENTAS_DIRECCION` con 5 cuentas.
Se agregaron:

| Nombre | Correo | DNI |
|---|---|---|
| Diana Marifer Bautista Garcia | diana.bautista@gmail.com | 73721183 |
| Aaron Galindo Conde | aaron.galindo@gmail.com | 71294422 |
| Ruth Esther Galindo Conde | ruth.galindo@gmail.com | 61144254 |
| Reyna Galindo Conde | reyna.galindo@gmail.com | 62262684 |

Sigue siendo idempotente: correr el seeder de nuevo no duplica ninguna
cuenta ya creada. La contraseña temporal de cada una se imprime una sola
vez en la consola al correr `php artisan db:seed --class="Database\Seeders\ProduccionSeeder" --force`.

### 5. Trait compartido `ImportaFilasDeExcel`

**Commit:** `522d99a`

Las 4 funciones de lectura de filas de Excel (celda opcional/obligatoria,
parseo de fechas, mensaje de error) vivían duplicadas dentro de
`MatriculaService`. Se extrajeron a `App\Shared\Support\ImportaFilasDeExcel`
para reutilizarlas en la carga masiva de Docentes (y después, Personal) sin
copiar y pegar código.

### 6. Módulos nuevos: Docentes y Contratos

**Commit:** `b442466`

- **Docentes**: listado, alta/edición y carga masiva por Excel (mismo
  patrón que Estudiantes). `Docente` extiende el perfil de `User`
  (especialidad, grado académico, fecha de ingreso) sin duplicar
  nombre/DNI/celular/estado, que siguen viviendo en `users`. Se creó (o
  reutilizó, si el DNI ya tenía cuenta) su acceso institucional
  (`{dni}@ceba.test`, contraseña inicial = DNI), igual que Estudiantes.
  Deliberadamente **no** se tocó `Horario.docente_id` (sigue apuntando a
  `users.id`) para no afectar Académico/AulaVirtual/Evaluaciones/Asistencia.
- **Contratos**: módulo simple ligado a un Docente — tipo, vigencia,
  monto, observaciones y el documento firmado (PDF) adjunto.
- Permisos nuevos: `docentes.ver`/`docentes.gestionar`,
  `contratos.ver`/`contratos.gestionar`.

### 7. Recursos Humanos (sidebar) + módulo Personal

**Commit:** `45dec7d`

- Docentes y Contratos se movieron de la sección "Matrícula" a una sección
  nueva "Recursos Humanos", ubicada justo arriba de "Administración".
- Módulo nuevo **Personal**: directorio de personal adicional de la
  institución (portería, limpieza, psicología, etc.) que **no** inicia
  sesión en el sistema — a diferencia de Docentes, no extiende a un `User`,
  guarda su propia identidad (nombres, DNI, cargo, área, fecha de ingreso,
  activo). Incluye carga masiva por Excel con el mismo patrón que Docentes.
- Permisos nuevos: `personal.ver`/`personal.gestionar`.

**Incidente de despliegue:** tras subir el código a producción (Hostinger),
`/personal` devolvía 500 porque la migración de la tabla `personal` no se
había corrido (`SQLSTATE[42S02]: Base table or view not found`). Se
diagnosticó vía `storage/logs/laravel.log` y se resolvió corriendo
`php artisan migrate --force` en el servidor. Lección para el checklist de
despliegue: **siempre correr las migraciones nuevas antes de probar el
módulo**, incluso si el resto del código ya se subió.

### 8. Recibo de pago en dos series (original + copia)

**Commits:** `1e4fe77`, `c77be99`

El recibo de pago ahora se emite como una libreta física de original y
copia: el mismo PDF trae dos páginas, serie **001 "Recibo de pago"** (para
el apoderado) y serie **002 "Recibo"** (copia de la institución), ambas con
el mismo correlativo (`App\Modules\Pagos\Enums\SerieReciboEnum`).

- El correlativo (`Recibo::numero_recibo`) dejó de reiniciarse cada año:
  con dos series compartiéndolo, reiniciarlo generaría números repetidos y
  rompería el `unique()` de la columna.
- Comando nuevo `php artisan recibos:regenerar`: migra los recibos ya
  emitidos (datos de prueba) al nuevo formato — reasigna el correlativo en
  orden de creación y regenera el PDF de cada uno. Ya se corrió sobre los
  13 recibos de prueba locales.

**Efecto secundario encontrado y corregido en el camino:** al arreglar que
los comandos Artisan de cada módulo (`app/Modules/*/Console/Commands`)
nunca se registraban (ver más abajo), un primer intento de la corrección
apuntaba el escaneo a `app/Modules` completo. En Windows (filesystem
insensible a mayúsculas/minúsculas) eso hizo que el detector de comandos
confundiera un `Routes/web.php` con una clase, lo re-ejecutara **fuera**
del grupo de middleware `web`, y pisara la ruta `/dashboard` ya registrada
— dejándola sin `VerificarCuentaActiva`, así que una cuenta desactivada ya
no se desconectaba sola. Detectado por el suite de tests completo antes de
subir nada; la corrección final acota el escaneo a las carpetas
`*/Console/Commands` de cada módulo en vez de todo `app/Modules`.

### 9. Bug real encontrado: comandos de módulos nunca se registraban

**Commit:** `c77be99`

Laravel solo escanea `app/Console/Commands` por defecto para descubrir
comandos Artisan. El comando `whatsapp:recordatorios`
(`app/Modules/Notificaciones/Console/Commands/EnviarRecordatoriosWhatsapp.php`)
vive dentro de un módulo, así que **nunca estuvo registrado** — el cron
(`Schedule::command('whatsapp:recordatorios')`) llevaba tiempo intentando
ejecutar un comando que Artisan no conocía. Se agregó cada carpeta
`Console/Commands` de los módulos a `withCommands()` en `bootstrap/app.php`.
Verificado con `php artisan list | grep whatsapp`.

De paso, se subió el `memory_limit` de PHPUnit a 1024M (`phpunit.xml`):
DomPDF no libera toda su memoria interna entre renders dentro de un mismo
proceso, y con cientos de tests generando PDFs (recibos, certificados,
libretas) en el mismo proceso, el límite por defecto de 512M se agotaba
antes de terminar el suite completo. No afecta producción, donde cada
request genera un PDF a la vez.

### Verificación de toda la sesión

- Suite completo: **812/812 tests pasando**.
- Pint y Larastan (`phpstan analyse app`) limpios en cada entrega.
- Verificación en vivo con Playwright: login exige el nombre, "Sesiones
  activas" lo muestra, alta real de un Docente y un Contrato desde la UI,
  botón "Exportar PDF" en Flujo de Caja, recibo con las dos series
  generado con datos reales.
- Desplegado a Hostinger (`cebapb.com`) por subida manual de archivos vía
  el Administrador de archivos de hPanel + comandos por SSH (el proyecto
  no tiene `git` configurado en el servidor, así que no se usa
  `git pull` ahí).
