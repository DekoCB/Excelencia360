# Bitácora de cambios

Registro cronológico (más reciente arriba) de los cambios importantes hechos
al sistema, con el detalle suficiente para entender el porqué sin tener que
releer todo el historial de Git. Cada entrada nueva se agrega arriba, con
fecha y los commits que le corresponden.

---

## 2026-09-18

### Certificado de estudios también muestra DNI y nombres/apellidos separados

En la página pública de verificación, el Certificado de Capacitación ya
mostraba Documento de Identidad, Nombres y Apellidos del participante por
separado; el Certificado de estudios en cambio solo mostraba "Estudiante:
{nombre completo}". El usuario pidió que el certificado de estudios también
muestre esos mismos datos de identidad.

- `certificados/verificar.blade.php`: DNI/Nombres/Apellidos ahora se
  muestran para **cualquier** tipo de documento verificado, no solo
  capacitación. Los campos propios de capacitación (Número de Registro,
  Nombre del Curso, Horas Lectivas, Documento de Autorización) siguen
  siendo exclusivos de ese tipo — un certificado de estudios no tiene un
  "curso de capacitación" ni un número de registro que mostrar ahí, así
  que forzarlos habría dejado guiones vacíos sin sentido. Lo propio del
  certificado de estudios (N.° de certificado, Semestre, Ciclo, Fecha de
  emisión) se mantiene igual. La sección de Convenios (si hay logos
  configurados) ahora también es visible para cualquier tipo, no solo
  capacitación.
- 2 tests existentes ajustados (verificaban el nombre completo como una
  sola cadena; ahora se separan en nombres/apellidos, que es justamente lo
  nuevo) y 1 test nuevo cubriendo el caso de certificado de estudios
  explícitamente. 112/112 tests de Certificados en verde.
- Verificado en vivo contra un certificado real de la BD de desarrollo.

---

## 2026-09-17 (cont. 4)

### Módulo nuevo "Apoderados" y renombrado "Tutores/Apoderados" → "Hijos"

El usuario notó que el único enlace bajo "Portal de Apoderados" decía
"Tutores/Apoderados" pero en realidad llevaba a la pantalla de **hijos**
(notas, pagos, documentos), no a una gestión de apoderados — se renombró a
"Hijos". Eso dejó en evidencia un hueco real: no había ninguna pantalla para
crear o corregir el apoderado de un estudiante ya matriculado (solo se
capturaba una vez, durante el asistente de matrícula o la carga masiva).

- **Pantalla nueva "Apoderados"** (`/matricula/apoderados`, arriba de
  "Hijos" en el menú, solo para Coordinación/Dirección — un apoderado no
  ve este directorio, solo "Hijos"): catálogo con tarjetas igual que
  Estudiantes, buscador por nombre/DNI/hijo, y un formulario para crear o
  editar. Reutiliza `MatriculaService::registrarApoderado()` para ambos
  casos (crear y editar): como `Apoderado.estudiante_id` es único y ese
  método hace `updateOrCreate()`, no hizo falta un método nuevo para
  editar. El selector de "nuevo apoderado" solo ofrece menores de edad que
  todavía no tienen uno (`estudiantesSinApoderado()`, método nuevo);
  editando, el estudiante queda fijo (no se puede reasignar a otro).
- **Ficha del estudiante**: la sección "Apoderado" se movió para aparecer
  justo debajo de "Datos personales" (antes iba después de
  "Observaciones"), y ahora también muestra correo y dirección, no solo
  nombres/DNI/parentesco/celular.
- 10 tests nuevos (permisos, crear, editar, filtrado del selector,
  búsqueda), Pint y Larastan limpios.
- Verificado en vivo contra la BD real de desarrollo: crear un apoderado
  nuevo → aparece en la ficha del estudiante justo donde corresponde →
  editar ese mismo apoderado → confirmado en la lista. Durante la
  verificación se tocó por error un registro real ajeno (un clic de
  prueba cayó sobre otra tarjeta antes de que el buscador terminara de
  filtrar) — detectado y revertido de inmediato antes de seguir; el dato
  de prueba propio se eliminó al terminar.

---

## 2026-09-17 (cont. 3)

### Programa de Estudio pasa a ser una carrera real (Académico)

El usuario preguntó si Aula Virtual estaba organizado como Académico
(Programa de Estudio → Semestre → Curso) y notó que además había un paso de
"Sección" (A/B) de más. Al revisar, lo que el menú llamaba "Programa de
Estudio" era en realidad el modelo `Ciclo` (un período de matrícula de ~6
meses, ej. "Grupo 1, Enero–Junio 2026") — el renombrado anterior (§8) fue
solo de texto, nunca existió un concepto real de carrera. El usuario pidió
que "Programa de Estudio" funcione como una carrera universitaria real, con
cursos adentro, y sin el paso de Sección en Aula Virtual.

Decisiones tomadas con el usuario antes de tocar nada: el `Ciclo` sigue
existiendo tal cual, solo se renombra en pantalla a **"Período de
Matrícula"**; un curso puede pertenecer a varias carreras (relación
muchos-a-muchos); cada carrera tiene sus propios semestres; los semestres y
cursos existentes se migran a un **"Programa de Estudio General"**
provisional (no se inventó un nombre real de carrera); en Aula Virtual el
recorrido queda Programa → Semestre → Curso, con el período de matrícula
resuelto automáticamente (el activo, o si ninguno lo está, el más reciente).

- **Modelo nuevo**: `ProgramaEstudio` (`programas_estudio`: nombre, activo),
  con su propia pantalla CRUD (`academico.programas-estudio.index`).
  `Grado` gana `programa_estudio_id`; `orden` pasa de único global a único
  *por programa*. `Curso` pierde su FK directa `grado_id` y pasa a una
  tabla pivote `curso_grado` (muchos-a-muchos) — se migró automáticamente
  cada vínculo curso→grado que ya existía, sin perder nada.
- **Alcance real, no solo Aula Virtual**: 36 archivos referencian
  `ciclo_id`, 31 `grado_id`. Los puntos que sí necesitaron cambio real:
  - `MigracionService::gradoSiguiente()` (promoción de semestre) ahora
    filtra también por `programa_estudio_id` — antes de este fix, con más
    de una carrera, podía promover a un estudiante al semestre de *otra*
    carrera con el mismo número de orden.
  - `matricula/index.blade.php` (Búsqueda avanzada): nuevo filtro de
    Programa de Estudio con su propia cascada, y `cursosDisponibles()`
    corregido para consultar el pivote en vez de `Curso.grado_id`.
  - `academico/horarios/index.blade.php`: nueva validación (antes no
    existía) de que el curso elegido esté realmente vinculado al semestre
    elegido.
  - `CursoVirtualService` (Aula Virtual): sus tres métodos ganan el filtro
    por período de matrícula "actual" (el de estado activo, o si no hay
    ninguno, el de fecha de inicio más reciente) — antes no filtraba por
    período en absoluto, dependía enteramente del drill-down manual.
  - Formulario de matrícula (`wizard.blade.php`): el selector de semestre
    ahora muestra también el programa ("Contabilidad — Semestre 1") para
    no confundir semestres con el mismo número en carreras distintas.
  - Verificado que NO necesitaban cambios: `FiltroMatriculaAcademico`
    (filtra por `Horario.grado_id`, nunca por `Curso.grado_id`),
    `Pagos\CobranzaService` (mismo patrón), Asistencia/Incidencias/
    Notificaciones (`grado_id` solo como filtro simple),
    `Grado::letraAula()/scopeDeSeccion()` (el A/B de aulas físicas es un
    concepto distinto y ortogonal, sigue usándose en Migraciones — solo se
    dejó de consumir en la navegación de Aula Virtual).
- **Aula Virtual**: navegación reducida de 3 niveles (Programa→Sección→
  Semestre) a 2 (Programa→Semestre), sin el paso de Sección.
- **Menú de Académico reordenado**: Periodo Académico → Programa de
  Estudio (nuevo) → Semestres → Cursos → Aulas → Horarios → Período de
  Matrícula (Ciclo renombrado, se movió al final del grupo).
- Tests nuevos/actualizados en Academico, Migraciones, AulaVirtual y
  Matrícula (incluye casos de aislamiento entre programas: mismo orden de
  semestre en dos carreras distintas no debe chocar ni cruzarse). Pint y
  Larastan limpios en todo el proyecto.

---

## 2026-09-17 (cont. 2)

### Enlace externo en Biblioteca, además del PDF

El usuario preguntó si se podía añadir un PDF a la Biblioteca o enlaces, tras
confirmar que solo existía carga de PDF. Se agregó un campo de enlace externo
por libro, independiente del PDF (un libro puede tener PDF, enlace externo,
ambos o ninguno).

- Columna nueva `libros.enlace_externo` (nullable). `BibliotecaService::editarEnlaceExterno()`
  la actualiza (pasar `null` la quita).
- En el catálogo, junto a "+ PDF" y "+ Ejemplar" ahora hay un botón "+ Enlace"
  (o "Editar enlace" si ya tiene uno) que abre un mini formulario con
  validación `nullable|url|max:500`; guardarlo vacío no es posible salvo con
  el botón explícito "Quitar" (con confirmación).
  El enlace, cuando existe, se muestra junto al de "Descargar PDF" para
  cualquiera con `biblioteca.ver` (visible para todos, editable solo con
  `biblioteca.gestionar`), igual que el PDF.
- 6 tests nuevos (2 en `BibliotecaServiceTest`, 4 de permisos/validación en
  `BibliotecaPermisosTest`), todos en verde. Pint y Larastan limpios.
- Verificado en vivo con Playwright contra la BD real de desarrollo: crear
  libro → "+ Enlace" → guardar URL → aparece "Enlace externo" con el href
  correcto y el botón cambia a "Editar enlace" → libro de prueba eliminado al
  terminar.
- **Bug real preexistente encontrado al correr la suite completa (sin
  relación con este cambio):** `PlantillaCursoVirtualServiceTest::test_aplicar_recalcula_la_fecha_limite_de_la_tarea_segun_el_ciclo_destino`
  fallaba de forma intermitente porque el ciclo de origen usaba el año
  aleatorio por defecto de `CicloFactory` (`faker->year()`, entre 1970 y
  hoy); en algunos años al azar, Perú observó horario de verano con un
  cambio dentro de la ventana 1-15 de enero que usa el test, corriendo el
  `diffInWeeks()` de `guardarDesdeCursoVirtual()` una semana y rompiendo la
  fecha esperada sin que hubiera ningún cambio real de código. Corregido
  fijando el ciclo de origen a un año concreto (2026), igual que ya hacía
  el ciclo destino.

---

## 2026-09-17 (cont.)

### Bloque B, punto #2 — Importación masiva de Certificados de Capacitación

El usuario aclaró que el punto #2 del backlog original ("Excel de
importación masiva") no era para matricular estudiantes, sino para
emitir en lote los certificados de capacitación del punto #1 —
confirmó usar exactamente las mismas columnas que ya mostraba la
captura de #1: DNI, Nombres, Apellidos, Numero de Registro, Nombre del
Curso, Horas Lectivas y Documento de Autorizacion.

Nuevo `CertificadoService::emitirCapacitacionDesdeFilas()`, mismo
patrón que `EvaluacionService::calificarDesdeFilas()` (cada fila se
procesa de forma independiente, una fila inválida no afecta a las
demás): el estudiante se busca por DNI (debe existir ya, Nombres/
Apellidos del archivo son solo de referencia para quien arma la
plantilla); el curso se busca por nombre y, si no existe todavía, se
crea con las horas lectivas y el documento de autorización de esa
fila — si ya existe, no se sobrescribe con lo que traiga la fila
(evita que un typo en una sola fila corrompa el catálogo). Nuevo panel
"Importar certificados de capacitación en lote" en la pestaña "Emitir
certificado", con el mismo componente de importación
(`HojaConEncabezadosImport`, copia local del módulo Certificados,
mismo criterio que Evaluaciones/Matrícula: cada módulo tiene la suya en
vez de compartir una clase entre módulos).

8 tests nuevos. Suite completo: 1142/1142. Pint y Larastan limpios.
Verificado en vivo: se importó un archivo real
con las columnas exactas que pidió el usuario (con tildes/espacios,
para probar que el formateo automático de encabezados de Laravel Excel
las normaliza bien) y el certificado quedó emitido correctamente.

---

## 2026-09-17

### Bloque B, punto #1 — Certificado de Capacitación y su validación pública

El usuario mandó una captura con el formato exacto que debe mostrar la
Validación de Certificados al consultar por código (el punto #1 del
backlog original, bloqueado hasta ahora por falta de este formato):
Documento de Identidad, Nombres/Apellidos del Participante, Número de
Registro del Documento, Nombre y Horas Lectivas del Curso, Documento de
Autorización y logos de instituciones convenio. Antes de programar se
confirmaron 4 detalles con el usuario: (1) esto es un tipo de documento
nuevo, pero el participante **sí** debe ser un estudiante ya matriculado
en el sistema (no un registro suelto con datos tecleados a mano) — el
curso de capacitación en sí, en cambio, si debe modelarse como una
entidad propia, no texto libre; (2) en la captura "Nombres" y
"Apellidos" estaban con las etiquetas cambiadas, van intercambiados a
la convención normal; (3) los convenios con instituciones son una
lista fija para todos los certificados de este tipo, no varían por
curso; (4) el "Número de Registro" lo escribe el staff a mano desde su
propio registro externo, no lo genera el sistema.

**Se reutilizó por completo el módulo Certificados existente** en vez
de crear uno aparte: nuevo caso `CERTIFICADO_CAPACITACION` en
`TipoDocumentoEnum`, nuevo modelo `CursoCapacitacion` (catálogo propio,
nombre/horas_lectivas/documento_autorizacion — distinto de
`Academico\Curso`, que es una materia del currículo EBA con horario
propio, sin relación con esto) y dos columnas nuevas en `certificados`
(`curso_capacitacion_id`, `numero_registro`). `CertificadoService::
emitir()`, `duplicar()`, `verificar()` y el generador de PDF se
extendieron para el nuevo tipo sin tocar el comportamiento de los tipos
existentes — mismo `PlantillaCertificado::valoresPorDefecto()` (match
exhaustivo del enum, ahora con un caso más), mismo `renderizarCuerpo()`
con placeholders nuevos (`{{curso}}`, `{{horas_lectivas}}`).

**`verificar()` ahora busca por `codigo_verificacion` O
`numero_registro`** (antes solo por el primero): son "el código
impreso en el documento" desde el punto de vista de quien lo escanea o
lo tipea, sin importar qué tipo de certificado sea. `numero_registro`
quedó indexado pero no único a nivel de columna (mismo criterio que
`codigo_verificacion`): un duplicado reutiliza el mismo número a
propósito, y `verificar()` ya filtra `es_duplicado=false`, así que
nunca hay ambigüedad de cuál devolver.

**Panel de administración** (`certificados/index.blade.php`): pestaña
"Emitir certificado" ahora muestra campos distintos según el tipo
elegido (curso + número de registro para capacitación, matrícula para
los demás) — nueva pestaña "Cursos de capacitación" para gestionar el
catálogo (mismo permiso `certificados.gestionar_plantilla` que ya
gobierna la Plantilla, en vez de crear un permiso nuevo).

**Bug real encontrado y corregido verificando en vivo con Playwright:**
al cambiar el tipo de documento, el formulario pasa de mostrar el
select de Matrícula al de Curso de capacitación en la misma posición
del DOM — sin un `wire:key` propio por rama, Livewire reutilizaba el
nodo existente y el estado interno de Alpine del `<x-select-input>`
anterior (su lista de opciones) quedaba pegado al nuevo: el desplegable
de curso mostraba las opciones de matrícula. Se corrigió agregando
`wire:key` a cada rama — ver el comentario en el archivo, porque es el
tipo de bug que un test de Livewire normal (sin navegador real) no
detecta.

**Convenios con instituciones**: nuevo `config('institucion.convenios')`,
vacío por defecto (igual criterio que `blog: []`) — no se inventaron ni
se usaron logos de terceros sin autorización; queda listo para que el
usuario mande los logos reales y sus nombres.

38 tests nuevos. Suite completo: 1134/1134. Pint y Larastan limpios.
Verificado en vivo de punta a punta: se creó
el curso "Ofimática Nivel Avanzado" (130 horas, R.D.R. N°2182-2023-DREP),
se emitió un certificado de capacitación para un estudiante real con
número de registro 3002324002, y la página pública de Validación de
Certificados (sin sesión iniciada) mostró exactamente el formato de la
captura del usuario al consultar ese número.

Sigue pendiente el resto del Bloque B: #2 (columnas del Excel de
matrícula masiva) y #3 (el QR redirige a esta misma página, ya
correspondía desde que se agregó el enlace en el navbar) — #3 en
realidad ya está resuelto, dado que la URL de verificación siempre fue
la misma página pública, ahora con el nuevo formato según el tipo.

---

## 2026-09-16 (cont. 4)

### Bloque C, punto #11 (último) — Evaluaciones dentro de Cursos Virtuales, modo Físico/Virtual con banco de preguntas

El punto más grande del backlog de 14. Antes de tocar código se le
preguntó al usuario un detalle que cambiaba el alcance: hoy el Aula
Virtual de un curso es opcional (el docente la "activa" aparte), así
que si Evaluaciones pasa a vivir 100% adentro, un docente que solo
quiere registrar notas físicas quedaría obligado a activarla igual.
Eligió que sí dependiera por completo — se descubrió después que esto
ya no era un problema real: `HorarioService::crear()` activa el aula
virtual automáticamente desde antes de esta tarea, así que en la
práctica todo horario ya tiene una. También se preguntó cuántos
intentos tiene un estudiante en una evaluación Virtual: eligió uno
solo, bloqueado al enviar.

**Se retiró por completo la pantalla horario→evaluaciones** (`evaluaciones.index`
el picker por ciclo/sección/grado, y `evaluaciones.show` la de crear y
calificar) — ya no tenía sentido como flujo aparte. En su lugar:
- Nueva pestaña "Evaluaciones" dentro de `aula-virtual/show.blade.php`
  (mismo patrón que Materiales/Tareas: crea con nombre, fecha, tipo y
  sección opcional, agrupa por Sección igual que el resto).
- Nueva página propia por evaluación (`aula-virtual/{curso}/evaluaciones/{evaluacion}`,
  mismo patrón que `aula-virtual.tarea`) donde vive todo lo pesado:
  calificar (Físico) o armar preguntas/revisar resultados (Virtual).
- "Mi libreta" y la libreta que ve el staff **no se tocaron** — son un
  resumen por ciclo que cruza TODOS los cursos del estudiante, no le
  pertenecen a un solo curso virtual.

**`Evaluacion` ganó `curso_virtual_id`, `seccion_id` y `tipo`, pero
mantuvo `horario_id`.** Decisión deliberada para acotar el riesgo: los
promedios, la libreta, el widget de "Mis evaluaciones" del dashboard y
Calendario ya funcionaban bien operando sobre `horario_id`, y no tienen
nada que ver con dónde vive la pantalla de gestión — tocarlos todos
para forzarlos a pasar por `curso_virtual_id` habría sido un refactor
mucho más grande y riesgoso sin ningún beneficio visible. `curso_virtual_id`
se deriva de `horario_id` en el momento de crear (1:1, nunca se
desincroniza) y es lo único que la nueva UI necesita. 64 evaluaciones
existentes en la base real, backfill automático en la migración
(sin excepciones: todo horario ya tenía su aula virtual).

**Fisico vs Virtual — el banco de preguntas es 100% nuevo, no existía
nada parecido en el proyecto** (se buscó en Encuestas, Formularios,
ExamenUbicacion — nada). Modelos nuevos: `Pregunta` (opción_múltiple /
opción_única / pregunta_abierta, con puntaje propio), `Alternativa`,
`IntentoEvaluacion` (existe recién cuando el estudiante envía — su sola
existencia es el candado de "un solo intento", no hizo falta un estado
"borrador" aparte) y `RespuestaEstudiante` (alternativas elegidas en
JSON, o texto libre). `PreguntaService` valida al guardar: mínimo 2
alternativas, al menos una correcta, y opción única no admite más de
una marcada correcta.

**Autocalificación escalada a la nota vigesimal existente, no un
sistema de puntos aparte:** `IntentoEvaluacionService::enviar()` califica
opción_múltiple/única al toque comparando el conjunto elegido contra el
marcado correcto (todo o nada, sin crédito parcial); una pregunta
abierta queda pendiente hasta que el docente le pone puntaje a mano
(`calificarAbierta()`). Recién cuando TODAS las preguntas de un intento
tienen puntaje, se calcula `(puntaje obtenido / puntaje total) × 20` y
se llama al mismo `EvaluacionService::calificar()` de siempre — así toda
la maquinaria existente (promedios, libreta, `NotaLetraEnum`) sigue
funcionando sin cambios, sin enterarse de que esa nota vino de un
examen autocalificado.

**Escaneo de cámara reutilizado, esto no:** a diferencia de #9 (QR),
acá no había nada que reaprovechar de otro módulo — se construyó desde
cero pero reutilizando el patrón arquitectónico de Tareas (entrega +
calificación manual) para el lado de preguntas abiertas.

Tests nuevos (banco de preguntas, autocalificación, flujo completo
Livewire de crear pregunta → rendir → calificar abierta) más la
reescritura de 7 archivos de test existentes que apuntaban a las
pantallas retiradas, y el borrado de uno que solo probaba el picker
horario→evaluaciones ya retirado (`EvaluacionesSeccionesTest`, su
equivalente ya vive en las pruebas de `aula-virtual.index`). Se
aprovechó para borrar dos policies/gates que quedaron sin ningún
consumidor (`HorarioEvaluacionesPolicy` y sus dos `Gate::define` en
`EvaluacionesServiceProvider`). Suite completo: 1113/1113 (subió de
1097 a 1113: 16 tests netos nuevos tras sumar y restar la
reestructuración). Pint y Larastan limpios. Verificado en vivo de punta
a punta: el docente crea una evaluación Virtual, agrega una pregunta de
opción única con su alternativa correcta marcada, un docente sin
`evaluaciones.publicar` no ve el botón «Publicar» pero un Coordinador
sí, y una vez publicada el estudiante la rinde y ve su nota (20.00) al
instante.

Con esto se cierra el Bloque C completo y el backlog de 14 puntos —
quedan solo los puntos del Bloque B (#1 lógica de consulta, #2 Excel de
importación) pendientes de que el usuario mande los formatos exactos.

---

## 2026-09-16 (cont. 3)

### Bloque C, punto #9 — Asistencia por QR (estudiantes y docentes)

Antes de programar se le preguntó al usuario quién escanea el QR (el
estudiante mostraría un QR fijo tipo carnet; ¿lo escanea el propio
docente desde su celular, o un dispositivo compartido en el aula?) y
lo mismo para docentes (¿lo escanea el personal de oficina, o el
docente se autoregistra?). Eligió, para ambos casos, la opción que
reutiliza la sesión ya autenticada de quien hoy hace el registro
manual: **el docente escanea desde su propio celular** en la misma
página donde ya pasa lista, y **el personal de oficina escanea al
docente** en la misma página donde ya marca la asistencia de todos —
en los dos casos el QR reemplaza el paso manual de tipear/seleccionar,
no cambia quién es responsable de registrar.

**Token de QR, no el DNI:** cada `Estudiante` y `Docente` gana un
`qr_token` propio (nuevo trait `App\Shared\Support\TieneQrToken`,
generado perezosamente la primera vez que se pide, no en la creación
del modelo — así no hace falta backfill ni tocar los servicios que ya
los crean). Se usó un token aparte en vez del DNI (que ya es el dato
que el estudiante tipea a mano en el autorregistro por
`asistencia.marcar`) porque un QR puede terminar fotografiado o
capturado en pantalla, y el DNI es más sensible que conviene no
exponer ahí.

**Generación del QR:** se reutilizó tal cual `App\Shared\Support\
QrCode::pngBase64()`, la misma clase que ya dibuja el QR de
verificación de certificados (bacon/bacon-qr-code + GD a mano, sin
librerías nuevas). Cada estudiante/docente ve su QR en una tarjeta
nueva "Mi código de asistencia" en Mi perfil.

**Escaneo (lectura de cámara):** no existía nada de esto en el
proyecto — la parte de certificados solo genera, nunca decodifica. Se
agregó `jsqr` (única dependencia npm nueva, sin dependencias propias)
y un componente Alpine (`lectorQr` en app.js) que lee la cámara cuadro
a cuadro sobre un `<canvas>` oculto y llama al método Livewire
indicado; nuevo `<x-qr-scanner metodo="...">` genérico para no
duplicar esa lógica entre el escaneo de estudiantes y el de docentes.

**Persistencia:** para estudiantes, el escaneo llama al mismo
`AsistenciaService::autorregistrar()` que ya usaba el autorregistro por
DNI (mismo criterio: nunca pisa un registro que el docente ya hizo a
mano, marca tardanza según el margen de tolerancia existente) — el
docente solo puede escanear si el estudiante está matriculado en ese
horario y si la fecha seleccionada es hoy. Para docentes, nuevo
`AsistenciaDocenteService::registrarPorQr()` (no había autorregistro
previo que reutilizar): marca presente con la hora real de escaneo,
tampoco pisa un registro ya existente para el día. Ambos casos
degradan con un aviso visible (código no reconocido / no matriculado)
en vez de fallar en silencio.

17 tests nuevos (trait de token, servicio y permisos de ambos módulos
de asistencia, tarjeta en Mi perfil). Suite completo: 1097/1097. Pint
y Larastan limpios. Verificado en vivo: QR visible en Mi perfil de un
estudiante y de un docente, botón "Escanear QR" visible solo en la
fecha de hoy en ambas páginas de asistencia, y manejo correcto del
error cuando el navegador no puede acceder a la cámara (probado en
Chromium headless, sin dispositivo de cámara disponible).

Queda pendiente el último punto del Bloque C: #11 (evaluaciones dentro
de Cursos Virtuales, con modo Físico/Virtual y 3 tipos de pregunta).

---

## 2026-09-16

### Nuevo backlog de 14 puntos — Bloque A (cambios independientes) + entorno de Validación de Certificados

El usuario trajo una lista de 14 cambios grandes (renombrado del núcleo
académico, asistencia por QR, evaluaciones dentro de Aula Virtual,
materiales por sesión, etc.). Dado el tamaño y el riesgo de intentarlo
todo de una vez, se le propuso secuenciarlo por bloques; eligió empezar
por el Bloque A (los cambios independientes y acotados), dejando la
reestructuración académica grande para después.

**#4 — Portal de Apoderados accesible para Dirección, renombrado a "Tutores/Apoderados":**
La página `mis-hijos` daba 403 a Dirección porque, aunque tenía el
permiso `matricula.ver_propio_hijo` (vía el rol `*`), no tenía ningún
registro `Apoderado` propio vinculado. Se agregó un segundo modo
("directorio de staff", gateado por `reportes.historial_estudiante` —
el mismo permiso que ya gobierna la búsqueda general de
historial-estudiante, así que no se concedió ningún acceso nuevo) con
buscador por apoderado o por su hijo; el modo original ("mis hijos",
acotado a la cuenta) sigue igual para un Apoderado de verdad. Renombrado
en sidebar y encabezado. 5 tests nuevos.

**#5 — Clases grabadas por enlace, reproducibles ahí mismo:**
Nuevo `App\Shared\Support\VideoEmbed` reconoce YouTube/Vimeo/Google
Drive/archivo de video directo y devuelve un iframe o `<video>`
incrustado con un toggle "Ver video"/"Ocultar video"; un enlace no
reconocido (Zoom, etc.) sigue abriendo en pestaña nueva como antes. 9
tests nuevos.

**#10 — Biblioteca virtual (PDF), ícono de reloj, historial de descargas:**
`Libro` ahora acepta un PDF (MediaLibrary, colección `pdf`, reemplaza al
subir uno nuevo). Nueva tabla `descargas_libro` registra quién descargó
qué y cuándo, mostrado en una sección solo para quien gestiona. Ícono de
"Mis préstamos" cambiado de libro a reloj. 8 tests nuevos.

**#12 — Comprobantes de pago en 80mm y A4:**
`ReciboService::emitir()` ahora genera y guarda dos PDFs por recibo: el
A4 de siempre y uno nuevo en 80mm (plantilla compacta aparte,
`pdf.recibo-80mm.blade.php`, tamaño de papel fijo vía
`Pdf::setPaper([0,0,226.77,566.93])` — DomPDF no soporta alto
indeterminado como un rollo térmico real). `recibos:regenerar` también
regenera ambos, así que los 19 recibos reales existentes ya tienen su
versión 80mm. Nuevo componente `x-recibo-enlaces` centraliza los dos
enlaces en los 4 lugares que mostraban el recibo. 4 tests nuevos.

**#13 — Código de Documento de Aprobación en la plantilla de Certificados:**
Campo opcional nuevo en `PlantillaCertificado` (mismo patrón que
`pie_nota`): si se completa, se imprime debajo del N.° del certificado;
si se deja vacío, no aparece. 4 tests nuevos.

**#14 — Constancias de Prácticas Preprofesionales y Profesionales:**
Dos casos nuevos en `TipoDocumentoEnum`, con su plantilla por defecto y
agregados a `constancias()` (de donde ya se derivan automáticamente los
selectores de Constancias, sin listas duplicadas que actualizar a mano).
La columna `tipo` (varchar(30) en 3 tablas) se quedaba corta para
`constancia_practicas_preprofesionales` (37 caracteres): migración nueva
la amplía a varchar(50) vía SQL crudo (el proyecto no tiene
doctrine/dbal para el `->change()` de Blueprint), solo en MySQL —
SQLite (tests) no impone el límite de un VARCHAR. 5 tests nuevos.

**Entorno para #1 (Validación de Certificados):** la lógica de consulta
espera el formato que el usuario va a enviar, pero se preparó lo que sí
se sabía: enlace "Validación de Certificados" en el navbar público,
entre Blog y Contáctanos, apuntando a la página de verificación pública
ya existente. 1 test nuevo.

Quedan pendientes, para después: #1 (lógica de consulta) y #2 (Excel de
importación masiva) en cuanto el usuario mande los formatos; #3 (el QR
redirige a Validación de Certificados, depende de #1); y el resto del
Bloque C (materiales por sesión, secciones estilo Moodle, asistencia por
QR, evaluaciones dentro de Aula Virtual) — ver la entrada siguiente para
el primer punto del Bloque C, ya cerrado.

---

## 2026-09-16 (cont.)

### Bloque C, punto #8 — Renombrado académico: SIAGIE → Periodo Académico, Grupo → Programa de Estudio, Grado → Semestre

El usuario pidió seguir con el Bloque C (la reestructuración académica
grande) y eligió empezar por el renombrado, ya que los demás puntos
(#6, #7, #9, #11) dependen de la misma terminología.

**Alcance: solo texto visible en la UI, no un refactor de clases/tablas.**
Los modelos (`Siagie`, `Ciclo`, `Grado`), tablas, rutas y nombres de
variable internos se mantienen igual — cambiar eso habría sido un
refactor mucho más grande y riesgoso sin ningún beneficio visible para
el usuario. Se renombraron:
- Las 3 páginas propias del módulo Académico (título, botones, estados
  vacíos, mensajes flash) y su entrada en el sidebar.
- ~25 archivos consumidores en Matrícula, Reportes, Calendario,
  Asistencia, Evaluaciones, Aula Virtual, Migraciones, Notificaciones,
  Dashboard, Pagos, Vacaciones, Certificados/Constancias y varios PDFs
  (recibo, ficha de matrícula, historial del estudiante, libreta).
- 6 etiquetas y mensajes que no vivían en Blade sino fijos en código:
  `TipoCicloEnum::label()` ("Grupo 1 (Enero - Junio)" → "Programa 1
  (Enero - Junio)"), `ModalidadCicloEnum::label()` ("Grupo rotativo (6
  meses)"/"SIAGIE anual" → "Programa de Estudio (6 meses)"/"Periodo
  académico anual"), y 4 mensajes de validación en `CicloService`,
  `SiagieService` y `VacacionService` que mencionaban "SIAGIE"/"Grupo"
  directamente. Sin este paso, el renombrado se habría visto incompleto
  en cualquier pantalla que mostrara esos mensajes o el nombre generado
  automáticamente de un SIAGIE Anual.
- El seeder de demo (`AcademicoDemoSeeder`) para que instalaciones
  nuevas ya nazcan con "Semestre 1"–"Semestre 4" en vez de "Grado
  1"–"Grado 4".

**Deliberadamente NO tocado:** los nombres ya guardados en la base de
datos real (ej. un Ciclo llamado literalmente "Grupo 3 (Julio -
Diciembre)", un Grado llamado "Grado 1") — son datos editables por el
staff desde el propio CRUD de Programa de Estudio/Semestres, no texto
de plantilla; renombrarlos a la fuerza habría sido una migración de
datos de producción no pedida. El resultado visible: las etiquetas del
formulario y las columnas dicen "Programa de Estudio"/"Semestre", pero
un registro que el staff no ha vuelto a nombrar todavía puede seguir
mostrando "Grupo 3..." hasta que lo edite.

**Caso especial dejado sin cambiar a propósito:** el placeholder
`{{grado}}` que ofrece el editor de plantillas de Certificados/
Constancias no se renombró, porque es una clave funcional que el
backend reemplaza por `str_replace()` (`PlantillaCertificado::
renderizarCuerpo()`) — cambiar el nombre ahí habría roto cualquier
plantilla ya guardada que use `{{grado}}` en su cuerpo, sin ganar nada
más que una etiqueta más bonita.

Suite completo: 1083/1083 (sin cambios de conteo, ya que este punto es
puramente de renombrado). Pint y Larastan limpios. Verificado en vivo
con capturas reales de las 3 páginas y del sidebar.

Commit `b836634`, junto con el Bloque A completo del backlog de 14
puntos (entrada anterior).

---

## 2026-09-16 (cont. 2)

### Bloque C, puntos #6 y #7 — Materiales por sesión + secciones con nombre propio estilo Moodle

Se atacaron juntos porque son la misma pieza: reemplazar la agrupación
por "Semana N" (un entero fijo) por un modelo real de sección que el
docente crea a mano, con nombre propio ("Bienvenida", "Fin de curso",
etc.), fecha opcional, o ambos. Antes de programar se le preguntó al
usuario cómo debían crearse las secciones — generarlas automáticamente
desde el horario quedó descartado; eligió **creación manual, el docente
elige la fecha si quiere**.

**Modelo nuevo `Seccion`** (tabla `secciones`): pertenece a un
`CursoVirtual`, con `nombre` y `fecha` ambos opcionales pero no los dos
vacíos a la vez (`SeccionService::validarNombreOFecha()`), y `orden`
para el reordenamiento manual. `Seccion::titulo()` devuelve el nombre si
existe, si no la fecha formateada ("lunes 12 de enero"), si no
"Sección" — así una sección solo-fecha (pensada como "clase del día")
sigue siendo presentable sin que el docente tenga que inventarle un
nombre.

**Material, ClaseGrabada, Tarea y Foro** cambiaron su columna `semana`
(int nullable) por `seccion_id` (FK nullable a `secciones`, `nullOnDelete`
— borrar una sección no borra su contenido, lo deja en "Bienvenida" igual
que antes con `seccion_id` nulo). Mismo patrón en los 4 servicios
correspondientes y en el formulario de creación de cada uno dentro de
`aula-virtual/show.blade.php`, que ahora tiene un panel "Secciones"
nuevo arriba de las pestañas de contenido con crear/editar/reordenar
(↑/↓)/eliminar.

**"Crear para varios cursos a la vez" con secciones:** una sección
pertenece a un solo curso virtual, así que replicar contenido a otros
cursos no podía simplemente reusar el mismo `seccion_id`. Se agregó
`SeccionService::obtenerOCrearEquivalente()`, que busca (o crea) en cada
curso destino la sección equivalente por nombre+fecha antes de crear el
contenido ahí — decisión de diseño propia, no pedida explícitamente,
pero necesaria para que la función existente no quedara rota.

**Plantillas de curso** (`PlantillaMaterial`, `PlantillaClaseGrabada`,
`PlantillaTarea`, `PlantillaForo`) no tienen curso ni fecha real, así
que solo guardan `nombre_seccion` (string). Al aplicar una plantilla,
`SeccionService::obtenerOCrearPorNombre()` busca o crea la sección por
nombre en el curso destino. Caso especial: `PlantillaTarea` necesita
además recalcular la fecha límite según el ciclo destino, algo que antes
hacía con `semana` (desplazamiento fijo en semanas); ahora ese
desplazamiento se deriva de la sección real de la tarea al guardar la
plantilla (`diffInWeeks` entre el inicio del ciclo origen y la fecha de
la sección), y se sigue aplicando igual al aplicar — por eso
`PlantillaTarea` es la única de las 4 que conserva `semana` (ya no como
dato mostrado, solo como número interno para ese cálculo) además de
ganar `nombre_seccion`.

**Evaluaciones** ya tenía una fecha real propia y usaba `semana` solo
como agrupador visual redundante; se eliminó la columna y el campo del
formulario, y el agrupador de `evaluaciones/show.blade.php` pasó de
agrupar por semana a agrupar por la fecha real de cada evaluación.

9 tests que asumían `semana` (entero) se corrigieron para crear una
`Seccion` real y afirmar sobre `seccion_id`/`nombre_seccion` en su lugar
— no se relajó ninguna aserción, solo se adaptaron al nuevo modelo.
Suite completo: 1080/1080 (el conteo bajó de 1083 porque algunos tests
se renombraron en vez de duplicarse). Pint y Larastan limpios.
Verificado en vivo: crear una sección, verla en el panel con sus
controles, crear un material asignado a ella y confirmar que aparece
agrupado bajo su propio encabezado (no bajo "Bienvenida").

Queda pendiente el resto del Bloque C: #9 (asistencia por QR, propio y
de docentes) y #11 (evaluaciones dentro de Cursos Virtuales, con modo
Físico/Virtual y 3 tipos de pregunta).

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
