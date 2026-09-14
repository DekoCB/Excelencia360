# Notas de despliegue a producción

Destino: **Hostinger, hosting Cloud/Business (hPanel, compartido)** — sin
acceso root, sin Supervisor/systemd propio, pero con SSH, cron y control del
document root por dominio. Todo lo de abajo asume ese entorno; la sección
final cubre qué cambia si en algún momento se pasa a un VPS.

## 1. Checklist de despliegue (orden recomendado)

1. Crear la base de datos MySQL y su usuario desde hPanel → anotar
   host/nombre/usuario/contraseña.
2. Subir el código (Git desde hPanel si el plan lo soporta, o SSH + `git
   clone`/`rsync`). **No** subir `node_modules/`, `vendor/` ni `.env`.
3. Fijar el **document root del dominio a `/public`** (hPanel → Sitios web →
   [dominio] → Avanzado → Document root). Si el plan no permite cambiar el
   document root, ver la alternativa en la sección 4.
4. Elegir **PHP 8.2 o superior** para el dominio (hPanel → PHP Configuration).
5. Por SSH, en la raíz del proyecto:

   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env   # y completar según la sección 2
   php artisan key:generate
   php artisan storage:link
   php artisan migrate --force
   php artisan db:seed --class="Database\Seeders\ProduccionSeeder" --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

   `storage:link` es crítico: sin él, todo lo que sirve Media Library
   (recibos, libretas, certificados, comprobantes, QR de cuentas bancarias)
   devuelve 404 aunque el archivo exista.

   Si este comando falla con `Call to undefined function ...\exec()` (o
   `symlink()`): Hostinger deshabilita ambas funciones en `disable_functions`
   por seguridad en varios planes, y el fallback de Laravel para crear el
   symlink también pasa por `exec()` — así que ninguna de las dos rutas
   funciona desde PHP. Solución: crear el symlink directo por SSH, que no
   tiene esa restricción:

   ```bash
   ln -s /home/tu-usuario/ceba-app/storage/app/public /home/tu-usuario/ceba-app/public/storage
   ```

   Verificar con `ls -la public/storage` (debe verse como un enlace, no una
   carpeta). No bloquea el resto del checklist: los demás comandos de esta
   sección no dependen de este symlink.

   `db:seed --class=ProduccionSeeder` es igual de crítico y **fácil de
   saltarse**: sin él no existen los roles/permisos de los que depende todo
   `hasRole()`/`hasPermissionTo()` del sistema, y no hay ninguna cuenta con
   la que entrar. **Nunca** corras `php artisan db:seed` a secas (sin
   `--class`) en producción — eso ejecuta `DatabaseSeeder`, que está
   bloqueado fuera de local/testing (ver `DatabaseSeeder::run()`) porque
   mezcla lo anterior con estudiantes/pagos/evaluaciones ficticios. La
   contraseña temporal de cada cuenta de Dirección se imprime una sola vez
   en la consola al correr el seeder — cópialas antes de que se pierda la
   terminal, y cámbialas apenas inicien sesión. Si se agrega una cuenta
   nueva al arreglo `CUENTAS_DIRECCION` de `ProduccionSeeder` más adelante,
   correr el seeder de nuevo es seguro: las que ya existen se saltan sin
   duplicarse ni pisar su contraseña, solo se crean las nuevas.

   Verificar también en hPanel → PHP Configuration que estén activas las
   extensiones `bcmath`, `gd`, `zip`, `exif`, `fileinfo`, `mbstring`, `dom`,
   `xml` y `simplexml` (las que piden Composer/Larastan/DomPDF/Excel/2FA —
   confirmar con `composer check-platform-reqs` en local antes de subir).

6. Compilar el frontend **antes de subir**, en tu máquina local (ver
   sección 3) — no asumas que el plan de Hostinger tiene Node.js accesible
   por SSH.
7. Dar permisos de escritura a `storage/` y `bootstrap/cache/` para el
   usuario con el que corre PHP-FPM (normalmente el mismo usuario del
   hosting; `chmod -R 775` suele bastar, no hace falta 777).
8. Configurar **un único cron job** en hPanel (ver sección 5).
9. Activar SSL (Hostinger lo ofrece gratis vía Let's Encrypt) y forzar HTTPS.
10. Verificar `https://tu-dominio/up` → debe responder 200 (valida BD, caché
    y cola — ver `App\Listeners\DiagnosticarSaludDelSistemaListener`).

## 2. Variables de entorno de producción

Puntos que **sí o sí** cambian respecto a `.env.example` (pensado para
local):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1          # o el host que indique hPanel
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
# En Hostinger mysqldump normalmente SÍ está en el PATH de Linux —
# a diferencia de Windows/XAMPP, DB_DUMP_BINARY_PATH no debería hacer
# falta. Si `php artisan backup:run` falla buscando mysqldump, recién
# ahí definirla apuntando al binario real (`which mysqldump` por SSH).

SESSION_DRIVER=database
QUEUE_CONNECTION=database   # no hay Supervisor: ver sección 5, no uses redis aquí
CACHE_STORE=database

MAIL_MAILER=smtp           # con las credenciales SMTP que da Hostinger,
MAIL_HOST=...               # o un proveedor transaccional externo — sin
MAIL_PORT=587                # esto no llegan los correos de "olvidé mi
MAIL_USERNAME=...            # contraseña" (routes/auth.php ya los usa)
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls

# WhatsApp: dejar en null hasta tener cuenta de WhatsApp Business real.
WHATSAPP_DRIVER=null

BACKUP_ARCHIVE_PASSWORD=... # recomendable en producción; en local se deja vacío
```

**`APP_KEY` es de una sola vez**: generarla con `php artisan key:generate`
la primera vez y no volver a correr ese comando después. Cambiarla
invalida las sesiones activas y, más importante, **hace irrecuperables**
`two_factor_secret` y `two_factor_recovery_codes` de cualquier usuario que
ya haya activado 2FA (van cifrados con esa clave — ver
`App\Models\User::casts()`).

## 3. Build del frontend

`public/build/` está en `.gitignore` — no se sube por Git. Antes de cada
despliegue, en local:

```bash
npm ci
npm run build
```

y subir el `public/build/` resultante junto con el resto del código (por
SSH/SFTP, o como parte del mismo commit si se decide dejar de ignorarlo
para este repo en particular). Si confirman que el plan de Hostinger sí
tiene Node.js por SSH, `npm ci && npm run build` se puede correr ahí
directamente en vez de subir la carpeta ya compilada.

## 4. Si no se puede fijar el document root a `/public`

Algunos planes de hosting compartido no dejan apuntar el dominio a una
subcarpeta. Si ese es el caso en la práctica:

1. Subir todo el proyecto **fuera** de `public_html` (por ejemplo, a
   `~/ceba-app`).
2. Copiar el *contenido* de `ceba-app/public/` dentro de `public_html/`.
3. En `public_html/index.php`, ajustar las dos rutas que apuntan a
   `vendor/autoload.php` y `bootstrap/app.php` para que apunten a
   `../ceba-app/vendor/autoload.php` y `../ceba-app/bootstrap/app.php`.

Es el mismo truco estándar que se usa para cualquier Laravel en hosting
sin control de document root — évitalo si el punto 3 del checklist
funciona, que es más simple y no duplica el `.env` fuera de la raíz del
proyecto.

## 5. Colas y tareas programadas (sin Supervisor)

Hostinger (hosting compartido/Cloud) no da forma de mantener vivo un
proceso en segundo plano (`php artisan queue:work` normal, o
`php artisan horizon`). La solución ya está en el código: en vez de un
worker persistente, `routes/console.php` programa una ráfaga corta de
`queue:work --stop-when-empty` cada minuto, disparada por el *scheduler*
de Laravel.

Eso significa que en el panel de Hostinger (hPanel → Avanzado → Cron Jobs)
solo hace falta **una** entrada, la que exige cualquier app Laravel:

```
* * * * * cd /home/tu-usuario/ceba-app && php artisan schedule:run >> /dev/null 2>&1
```

Esa única línea dispara todo lo demás: los recordatorios de WhatsApp
(`whatsapp:recordatorios`, diario 8am), los backups (`backup:clean`/`run`/
`monitor`, de madrugada) y el procesamiento de la cola (cada minuto). No
hay que agregar cron jobs adicionales por cada tarea.

## 6. WhatsApp Business (cuando haya cuenta real)

1. `WHATSAPP_DRIVER=meta` en `.env`, más `WHATSAPP_TOKEN`,
   `WHATSAPP_PHONE_ID`, `WHATSAPP_VERIFY_TOKEN` y `WHATSAPP_APP_SECRET`
   desde el panel de Meta for Developers.
2. Registrar el webhook en Meta apuntando a
   `https://tu-dominio.com/webhooks/whatsapp` (ver
   `App\Modules\Notificaciones\Routes\webhook.php`) — solo funciona con
   HTTPS válido, así que confirmar el SSL primero.

## 7. Horizon y Redis (si en el futuro se pasa a VPS)

`laravel/horizon` requiere las extensiones PHP `pcntl` y `posix`. No
existen en Windows (por eso no está instalado en este repo) y en hosting
compartido tampoco hay forma de mantener el proceso `php artisan horizon`
vivo sin Supervisor/systemd — así que tampoco aplica en Hostinger Cloud/
Business. Si el proyecto crece y pasa a un VPS con acceso root:

```bash
composer require laravel/horizon
php artisan horizon:install
```

Luego:

1. `QUEUE_CONNECTION=redis` en `.env`, con Redis instalado y corriendo.
2. Las colas ya usadas por la app (`default`, `auditoria`) deben quedar
   declaradas en `config/horizon.php` bajo el/los supervisor(es).
3. Restringir el dashboard (`/horizon`) a Dirección, con un Gate en
   `app/Providers/AppServiceProvider.php`:

   ```php
   use Illuminate\Support\Facades\Gate;

   Gate::define('viewHorizon', fn ($user) => $user->hasRole('direccion'));
   ```

4. Mantener `php artisan horizon` vivo con Supervisor, con un `.conf` como:

   ```ini
   [program:ceba-horizon]
   process_name=%(program_name)s
   command=php /var/www/ceba/artisan horizon
   autostart=true
   autorestart=true
   user=www-data
   redirect_stderr=true
   stdout_logfile=/var/www/ceba/storage/logs/horizon.log
   stopwaitsecs=3600
   ```

   En ese punto, la entrada del cron de la sección 5 ya no hace falta para
   la cola (Horizon la reemplaza) — solo queda necesaria para
   `schedule:run` (recordatorios y backups).

## 8. Backups

`spatie/laravel-backup` está configurado (`config/backup.php`) y
programado (sección 5). En Hostinger, guardar el backup en el mismo disco
que aloja la app y la BD no protege contra una falla del propio servidor.
Si se quiere un disco remoto real, Hostinger no ofrece S3 nativo, pero
`config/filesystems.php` puede apuntar a un bucket S3-compatible externo
(Backblaze B2, Cloudflare R2, AWS S3...) agregando ese disco a
`backup.destination.disks`.

## 9. Monitoreo

`/up` ya valida BD, caché y cola (`DiagnosticarSaludDelSistemaListener`).
Si se quiere un APM externo (Sentry, Flare...), instalar el paquete
correspondiente y configurar el DSN por `.env` — no incluido porque
requiere una cuenta real.

## 10. CI/CD

El workflow de GitHub Actions (`.github/workflows/ci.yml`) corre Pint,
Larastan y Pest en cada push/PR contra `main`. No despliega automáticamente
a Hostinger — el deploy sigue el checklist de la sección 1, a mano o vía
Git deploy de hPanel si el plan lo soporta.
