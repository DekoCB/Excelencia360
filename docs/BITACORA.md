# Bitácora de cambios

Registro cronológico (más reciente arriba) de los cambios importantes hechos
al sistema, con el detalle suficiente para entender el porqué sin tener que
releer todo el historial de Git. Cada entrada nueva se agrega arriba, con
fecha y los commits que le corresponden.

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
