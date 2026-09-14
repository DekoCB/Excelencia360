# Auditoría de Cumplimiento ISO/IEC 25010 — CEBA

Instituto CEBA — Sistema Integral de Gestión Académica

Repositorio: `C:\xampp\htdocs\CEBA` (rama `main`, commit `997d9c6`) · Fecha de auditoría: 25 de agosto de 2026

Auditoría estática de solo lectura basada en evidencia de código y configuración.

---

## 1. Información general

| Campo | Valor |
|---|---|
| Nombre del proyecto | CEBA — Sistema Integral de Gestión Académica (`composer.json:3`: `"ceba/sistema-academico"`) |
| Dominio | Gestión académica de un Centro de Educación Básica Alternativa (Perú) |
| Stack backend | Laravel 12.64.0 (PHP ^8.2), Livewire 3.8.2 + Volt 1.11.1, Spatie Permission 6.25, Spatie MediaLibrary 11.23, Spatie Backup 9.3, DomPDF 3.1, Maatwebsite Excel 3.1, Google2FA |
| Stack frontend | Blade + Alpine.js (vía Livewire) + Tailwind CSS 3.4 (con una dependencia huérfana de Tailwind v4, ver hallazgo DOC-5) + Chart.js |
| Base de datos | MySQL en desarrollo/producción; SQLite en memoria para tests |
| Arquitectura | Monolito modular: 13 módulos de dominio en `app/Modules/*` (Academico, Asistencia, AulaVirtual, Certificados, Dashboard, Evaluaciones, Identidad, Incidencias, Landing, Matricula, Notificaciones, Pagos, Reportes) |
| CI/CD | GitHub Actions (`.github/workflows/ci.yml`): Pint + Larastan + Pest en cada push/PR a `main`. Sin despliegue automático. |
| Infraestructura objetivo | Hosting compartido (Hostinger, hPanel), sin Docker/Kubernetes/Terraform |
| Tamaño de la suite de tests | 73 archivos en `tests/Feature` (100% de los 13 módulos con al menos un archivo), 1 archivo placeholder en `tests/Unit` |

---

## 2. Alcance

Esta auditoría cubrió, mediante inspección estática de código y configuración (sin ejecutar la aplicación en producción, sin pruebas de penetración activas, sin pruebas de carga):

- Estructura completa del proyecto (`app/`, `config/`, `database/`, `resources/`, `routes/`, `tests/`, `.github/`, `docs/`).
- Backend: Services, Repositories, Policies, Models, migraciones, seeders, jobs, listeners.
- Frontend: componentes Livewire/Volt, vistas Blade, Alpine.js, Tailwind.
- Autenticación, autorización (Spatie Permission), sesiones, 2FA.
- Base de datos: integridad referencial, índices, normalización, datos sensibles.
- Seguridad: secretos, CSRF, XSS, SQLi, CORS, rate limiting, mass assignment, validación, IDOR.
- Trazabilidad y auditoría (`Auditable` trait, `audit_logs`).
- Pruebas: tipo, cobertura por módulo, configuración.
- CI/CD, ausencia de Docker/IaC, documentación de despliegue.
- Integraciones externas: WhatsApp (Meta Cloud API), generación de PDF, importación/exportación Excel, Media Library.
- Documentación técnica existente (README, `docs/DESPLIEGUE.md`).

**Fuera de alcance** (no verificable desde el repositorio, ver sección 14): configuración real del servidor de producción, pruebas de penetración activas, pruebas de carga/rendimiento reales, auditoría de extensiones PHP transitivas de dependencias de terceros, estudios de usabilidad con usuarios reales, y cualquier evidencia que solo exista fuera del código (contratos, políticas internas, capacitación del personal).

---

## 3. Metodología

1. **Identificación de la norma** (ver advertencia importante más abajo).
2. Orientación inicial directa: estructura de carpetas, `composer.json`, `.env`/`.env.example`, `.gitignore`, configuración de CI.
3. Verificación directa de que `.env` nunca fue versionado en git (`git log --all -- .env` vacío) y que no hay secretos hardcodeados en un barrido superficial de `app/`, `config/`, `database/`, `routes/`.
4. Investigación en profundidad delegada a 6 revisiones especializadas de solo lectura, ejecutadas en paralelo, cada una con instrucciones explícitas de **no modificar ningún archivo** y de citar evidencia `archivo:línea` para cada afirmación:
   - Seguridad del backend (secretos, CSRF, XSS, SQLi, CORS, rate limiting, mass assignment, validación, IDOR, dependencias).
   - Autenticación, autorización y trazabilidad (Spatie Permission, sesiones, 2FA, auditoría).
   - Base de datos y modelos (integridad referencial, índices, normalización, backups, soft deletes).
   - Testing, CI/CD, despliegue y variables de entorno.
   - Arquitectura, acoplamiento, manejo de errores, rendimiento (N+1, transacciones).
   - Documentación, frontend/usabilidad, integraciones externas, manejo de archivos subidos.
5. Síntesis y contraste cruzado de los seis informes en esta auditoría única, resolviendo solapamientos y verificando consistencia entre hallazgos.
6. Clasificación de cada hallazgo contra el modelo de calidad ISO/IEC 25010 (8 características, 31 subcaracterísticas en la edición 2011).

### ⚠️ Advertencia importante sobre la naturaleza de ISO/IEC 25010

A diferencia de normas como ISO/IEC 27001 (con controles numerados y auditables como una checklist de cumplimiento binario), **ISO/IEC 25010 es un *modelo de calidad*, no una norma de certificación con cláusulas obligatorias verificables una a una**. Define 8 características y 31 subcaracterísticas de calidad del producto software (Adecuación funcional, Eficiencia de desempeño, Compatibilidad, Usabilidad, Fiabilidad, Seguridad, Mantenibilidad, Portabilidad — exactamente las que aparecen en la tabla de referencia que compartiste), pero **no define por sí misma qué valor de una métrica constituye "cumplimiento"**; eso lo debe definir la organización (mediante métricas propias, como las de la columna "Métrica" de tu tabla) o mediante la norma complementaria ISO/IEC 25023 (métricas de calidad), a la que no tengo acceso al texto oficial.

En consecuencia, esta auditoría:
1. **Usa la taxonomía pública y ampliamente documentada de las 8 características y sus subcaracterísticas** de ISO/IEC 25010:2011 (de dominio público en resúmenes oficiales de ISO, literatura académica y herramientas de calidad de software) — no tengo acceso al texto normativo pagado completo con numeración exacta de cláusulas.
2. Interpreta "CUMPLE" como *"hay evidencia consistente en el código de un nivel adecuado de esa subcaracterística"*, no como *"satisface la cláusula X.Y.Z del documento oficial"*.
3. Marca explícitamente como **NO VERIFICABLE** cualquier subcaracterística que ISO/IEC 25010 reconoce como dependiente de medición externa (pruebas de carga, estudios de usuario, disponibilidad en producción) que esta auditoría estática no puede confirmar.
4. Complementa el modelo de 25010 con una matriz de seguridad más granular y técnica (sección 5) porque la sección 4 de tu solicitud pedía un nivel de detalle (CSRF, XSS, SQLi, IDOR, etc.) más fino que las 5 subcaracterísticas de "Seguridad" de 25010 por sí solas.

Si necesitas una evaluación certificable formalmente contra el texto oficial y sus métricas asociadas (ISO/IEC 25023), se requiere adquirir y aplicar el documento normativo original — esto está fuera del alcance de lo verificable desde el repositorio.

---

## 4. Resumen ejecutivo

### Conteo de la matriz de cumplimiento (sección 5)

| Estado | Cantidad |
|---|---|
| **CUMPLE** | 20 |
| **CUMPLE PARCIALMENTE** | 25 |
| **NO CUMPLE** | 1 |
| **NO APLICABLE** | 0 |
| **NO VERIFICABLE** | 7 |
| **Total de requisitos evaluados** | **53** |

Sobre los 46 requisitos que sí pudieron evaluarse con evidencia de código (excluyendo los 7 NO VERIFICABLE): **~43.5% CUMPLE**, **~54.3% CUMPLE PARCIALMENTE**, **~2.2% NO CUMPLE**.

**Porcentaje estimado de cumplimiento ponderado** (CUMPLE = 1, CUMPLE PARCIALMENTE = 0.5, NO CUMPLE = 0, excluyendo NO VERIFICABLE del denominador):

```
(20 × 1 + 25 × 0.5 + 1 × 0) / 46 = 32.5 / 46 ≈ 71%
```

### Nivel general: **ACEPTABLE**

**Explicación técnica de la clasificación:** CEBA es un proyecto con una base de ingeniería notablemente sólida para su tamaño y contexto (un solo desarrollador/equipo pequeño, sin presupuesto de infraestructura dedicada): 100% de foreign keys con política de borrado explícita, 100% de modelos con `$fillable` (sin riesgo de mass assignment), CSRF activo sin excepciones injustificadas, sin XSS ni SQL injection detectables, autenticación con 2FA real y rate limiting funcional, un sistema de auditoría (`Auditable`) aplicado a 48 de 50 modelos, y una suite de 73 archivos de test de feature que cubre el 100% de los módulos, corriendo en CI en cada push. Estos son indicadores de **Fiabilidad**, **Seguridad** y **Mantenibilidad** por encima de lo típico en proyectos de este tamaño.

Sin embargo, el proyecto **no alcanza el nivel BUENO** porque existe un hallazgo de autorización de severidad alta con impacto directo en la característica de Seguridad — **una cuenta marcada como "inactiva" por un administrador no queda realmente bloqueada: sus sesiones existentes siguen siendo válidas y nada impide que inicie una nueva sesión** (`app/Models/User.php:75-78` define `estaActivo()` pero el método es código muerto, nunca invocado en el flujo de login) — junto con una condición de carrera real en la emisión de certificados sin bloqueo (`CertificadoService.php:392-398`), una operación multi-tabla sin transacción en el mismo servicio (`CertificadoService.php:63-108`), y un formulario de subida de archivos en Aula Virtual sin validación de tipo (`aula-virtual/show.blade.php:148,186`) que permitiría, en teoría, subir un archivo con extensión ejecutable. Ninguno de estos hallazgos es trivial de ignorar en un sistema que maneja datos académicos y financieros de estudiantes reales.

La combinación de **fundamentos sólidos + hallazgos puntuales pero serios sin corregir** es exactamente el perfil de **ACEPTABLE**: no es un proyecto deficiente ni mal estructurado, pero tampoco puede calificarse como BUENO o EXCELENTE mientras el hallazgo de cuentas desactivadas (el más severo) permanezca sin resolver.

---

## 5. Matriz de cumplimiento

### 5.1 Adecuación funcional (Functional Suitability)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| FS-1 | Completitud funcional | 13 módulos de dominio cubren matrícula, pagos, académico, evaluaciones, asistencia, aula virtual, certificados, incidencias, notificaciones, reportes, identidad | `app/Modules/*` | CUMPLE | — | Cobertura funcional amplia y coherente con el dominio de un CEBA. |
| FS-2 | Corrección funcional | Condición de carrera en numeración de certificados (sin lock) y emisión sin transacción pueden producir un número de certificado duplicado o datos inconsistentes | `app/Modules/Certificados/Services/CertificadoService.php:63-108,392-398` | CUMPLE PARCIALMENTE | Alto | Ver hallazgo crítico AR-1/AR-2. |
| FS-3 | Pertinencia funcional | Reglas específicas del dominio peruano de EBA: franjas horarias institucionales (Lunes-Miércoles/Martes-Jueves/Domingo), bloqueo de acceso por deuda, ciclos de 4 "Grupos" al año | `app/Modules/Academico/Enums/FranjaHorarioEnum.php`, `app/Modules/Pagos/Services/BloqueoAccesoService.php` | CUMPLE | — | Las funciones observadas son apropiadas al contexto, no genéricas. |

### 5.2 Eficiencia de desempeño (Performance Efficiency)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| PE-1 | Comportamiento temporal (evitar N+1) | N+1 confirmado: 2 queries por cada grado dentro de un `-&gt;map()`, y patrón similar en cálculo de libretas | `resources/views/livewire/dashboard/index.blade.php:375-422`; `app/Modules/Evaluaciones/Services/LibretaService.php:55-64` | CUMPLE PARCIALMENTE | Medio | `ReporteService.php` sí usa `-&gt;with()` consistentemente (contraejemplo positivo). |
| PE-2 | Utilización de recursos (paginación) | Listados de Pagos, Certificados y Constancias usan `-&gt;get()` sin límite ni paginación | `resources/views/livewire/pagos/index.blade.php:168,177`; `certificados/index.blade.php:273,281`; `constancias/index.blade.php:259,267` | CUMPLE PARCIALMENTE | Medio | `Matricula` y varios repositorios sí implementan `-&gt;paginate()` correctamente. |
| PE-3 | Capacidad (comportamiento bajo carga) | Sin pruebas de carga en el repositorio | — | NO VERIFICABLE | — | Requiere pruebas de carga reales contra un entorno desplegado; no verificable por inspección estática. |

### 5.3 Compatibilidad (Compatibility)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| COMP-1 | Coexistencia con otro software | Sin evidencia de conflictos de recursos con otro software en el mismo entorno | — | NO VERIFICABLE | — | Depende del servidor de producción real, no del código. |
| COMP-2 | Interoperabilidad | Integración con WhatsApp Cloud API (Meta) vía Gateway abstraído, importación/exportación Excel, generación de PDF | `app/Modules/Notificaciones/Gateways/MetaWhatsAppGateway.php`; `maatwebsite/excel`; `barryvdh/laravel-dompdf` | CUMPLE PARCIALMENTE | — | El *envío real* de WhatsApp está deliberadamente desactivado en el flujo actual (`EnviarMensajeWhatsappJob.php:27-31`) — el canal existe en código pero no está operativo hoy. |

### 5.4 Usabilidad (Usability)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| USA-1 | Capacidad de reconocer su adecuación | — | — | NO VERIFICABLE | — | Requiere estudio con usuarios reales. |
| USA-2 | Capacidad de aprendizaje | — | — | NO VERIFICABLE | — | Requiere estudio con usuarios reales. |
| USA-3 | Operabilidad (feedback de acciones en curso) | Solo 12 de 67 componentes Livewire usan `wire:loading`/`wire:dirty`; ausente en login y en el wizard de matrícula (6 pasos, sube hasta 4 archivos) | `resources/views/livewire/pages/auth/login.blade.php` (0 ocurrencias); `resources/views/livewire/matricula/wizard.blade.php` (0 ocurrencias) | CUMPLE PARCIALMENTE | Bajo | Sí presente parcialmente en `aula-virtual/show.blade.php:443`. |
| USA-4 | Protección contra errores de usuario | Validación (`$this-&gt;validate()`) confirmada antes de cada persistencia en los 6+ componentes revisados de distintos módulos | `matricula/wizard.blade.php` (7 validaciones), `pagos/index.blade.php`, `certificados/index.blade.php`, `usuarios/ficha-modal.blade.php` | CUMPLE | — | Patrón consistente en todo el código revisado. |
| USA-5 | Estética de la interfaz de usuario | — | — | NO VERIFICABLE | — | Evaluación subjetiva de diseño, fuera del alcance de una auditoría de código. |
| USA-6 | Accesibilidad | Labels asociados (`for`/`id`) consistentes en los formularios revisados; no se verificó ARIA, contraste de color ni navegación por teclado | `resources/views/livewire/pages/auth/login.blade.php:96`; `matricula/wizard.blade.php:499-573` | CUMPLE PARCIALMENTE | Bajo | Positivo en lo verificado; incompleto en lo no verificado (requeriría auditoría de accesibilidad dedicada, ej. axe-core). |

### 5.5 Fiabilidad (Reliability)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| FIA-1 | Madurez | 73 archivos de test de Feature cubriendo 13/13 módulos, CI ejecuta la suite en cada push/PR | `.github/workflows/ci.yml:38-79` | CUMPLE PARCIALMENTE | — | Buena cobertura de feature/integración, pero sin tests unitarios puros ni E2E, y con al menos 2 hallazgos de flujos sin proteger ante fallos parciales (Certificados). |
| FIA-2 | Disponibilidad | Sin acceso a métricas de uptime de producción | — | NO VERIFICABLE | — | No verificable desde el repositorio. |
| FIA-3 | Tolerancia a fallos | `DB::transaction()` usado correctamente en 11 archivos de Services (Matrícula, Pagos, Academico, Asistencia, AulaVirtual, Identidad, Notificaciones) | `app/Modules/Matricula/Services/MatriculaService.php`; `app/Modules/Pagos/Services/PlanPagoService.php` | CUMPLE PARCIALMENTE | Alto | `CertificadoService::emitir()` (multi-tabla: Certificado + PDF + actualización de SolicitudCertificado) **no** usa transacción — inconsistencia real si falla a la mitad. |
| FIA-4 | Capacidad de recuperación | `spatie/laravel-backup` configurado y programado diariamente (`backup:run` 01:30, `backup:clean` 01:00, `backup:monitor` 02:00) | `routes/console.php:16-18`; `config/backup.php` | CUMPLE PARCIALMENTE | Medio | Backup solo a disco `local` (mismo servidor, sin redundancia externa); notificación de fallo configurada a un email placeholder sin personalizar (`config/backup.php:222-223`, `your@example.com`). |

### 5.6 Seguridad (Security)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| SEC-1 | Confidencialidad de credenciales | `password`, `two_factor_secret`, `two_factor_recovery_codes` en `$hidden`; secretos 2FA cifrados con cast `encrypted` | `app/Models/User.php:50-55,68-70` | CUMPLE | — | Correcto. |
| SEC-2 | Confidencialidad de datos personales (DNI) | `dni` almacenado en texto plano en `users` y `estudiantes`, sin cast `encrypted` | `database/migrations/0001_01_01_000000_create_users_table.php:20`; migración de `estudiantes` | CUMPLE PARCIALMENTE | Bajo-Medio | Dato personal sensible sin cifrado a nivel de columna (mitigado si el acceso a BD está restringido a nivel de infraestructura, no verificable). |
| SEC-3 | Integridad referencial | 87/87 foreign keys con política de borrado explícita (`cascadeOnDelete`/`restrictOnDelete`/`nullOnDelete`) | `app/Modules/*/Database/Migrations/*.php` (muestra: `matriculas_table.php:15-21`, `pagos_table.php:15-22`) | CUMPLE | — | Sin excepciones detectadas. |
| SEC-4 | No repudio / trazabilidad de cambios | Trait `Auditable` aplicado en 48/50 modelos de negocio, captura usuario, evento, valores antes/después, IP, user agent | `app/Modules/Identidad/Support/Auditable.php:18-51`; `app/Modules/Identidad/Database/Migrations/2026_07_30_150000_create_audit_logs_table.php` | CUMPLE | — | Ver detalle en sección 11. |
| SEC-5 | Autenticación | `Auth::attempt()` con hashing bcrypt (cast `hashed`), sin credenciales hardcodeadas | `app/Livewire/Forms/LoginForm.php:38`; `app/Models/User.php:70` | CUMPLE | — | Correcto. |
| SEC-6 | Autenticación de dos factores | TOTP real (`pragmarx/google2fa-qrcode`) con 8 códigos de recuperación de un solo uso, secreto cifrado | `app/Modules/Identidad/Services/TwoFactorAuthenticationService.php:19,60-74` | CUMPLE | — | Opcional (no forzado por rol), lo cual es una decisión de producto válida, no un defecto. |
| SEC-7 | Rate limiting en login | `RateLimiter::tooManyAttempts` con 5 intentos, clave `email\|ip`, evento `Lockout` | `app/Livewire/Forms/LoginForm.php:36,79-95,100-103` | CUMPLE | — | Funcional, confirmado con evidencia real (no solo cosmético). |
| SEC-8 | **Invalidación de sesión al desactivar cuenta** | `estaActivo()` definido pero nunca invocado; `Auth::attempt()` no filtra por `estado`; desactivar un usuario no revoca sus sesiones activas | `app/Models/User.php:75-78`; `app/Livewire/Forms/LoginForm.php:38`; `app/Modules/Identidad/Services/UserManagementService.php:47-56` | NO CUMPLE | **Crítico** | Ver hallazgo crítico H-1. Único **NO CUMPLE** de toda la matriz. |
| SEC-9 | Invalidación de sesión al cambiar contraseña propia | `updatePassword()` no invoca revocación de otras sesiones; es una acción manual y separada | `resources/views/livewire/profile/update-password-form.blade.php:18-38` vs `active-sessions-form.blade.php:17` | CUMPLE PARCIALMENTE | Medio | El mecanismo de revocación manual existe, pero no se dispara automáticamente. |
| SEC-10 | Registro de intentos fallidos de login | Solo `RateLimiter` transitorio; sin persistencia en BD/log estructurado de intentos fallidos | `app/Livewire/Forms/LoginForm.php:36-46`; sin listener para `Illuminate\Auth\Events\Failed`/`Lockout` | CUMPLE PARCIALMENTE | Medio | El rate limiting existe y protege contra fuerza bruta, pero no hay trazabilidad histórica de esos intentos. |
| SEC-11 | CSRF | Middleware `web` por defecto de Laravel 12 activo sin overrides; única exclusión (webhook WhatsApp) justificada por no ser tráfico de navegador | `bootstrap/app.php:12-14`; `app/Modules/Notificaciones/Routes/webhook.php:8-9` | CUMPLE | — | Correcto. |
| SEC-12 | Autenticidad del webhook externo | El endpoint del webhook de WhatsApp no valida la firma HMAC (`X-Hub-Signature`) pese a que `services.whatsapp.app_secret` existe en config | `app/Modules/Notificaciones/Http/Controllers/WhatsappWebhookController.php:42-61`; `config/services.php:44` | CUMPLE PARCIALMENTE | Medio | Un tercero podría enviar payloads de webhook falsificados. Actualmente de impacto acotado porque el envío real está desactivado (ver COMP-2), pero el webhook de *entrada* sí está activo. |
| SEC-13 | XSS | Solo 1 uso de `{!! !!}` en todo el proyecto, y pasa por `e()` antes de `nl2br()` | `resources/views/pdf/certificado.blade.php:78` | CUMPLE | — | Sin riesgo detectado. |
| SEC-14 | SQL Injection | Sin `DB::raw`/`whereRaw`/`selectRaw`/`statement`/concatenación de SQL en todo `app/` | — | CUMPLE | — | Todo el acceso a datos usa Eloquent/query builder parametrizado. |
| SEC-15 | CORS | Sin `config/cors.php` publicado; el wildcard por defecto de Laravel (`allowed_origins: ['*']`) solo aplica a `api/*`/`sanctum/csrf-cookie`, rutas que no existen en el proyecto | — | CUMPLE PARCIALMENTE | Bajo (riesgo latente) | Inocuo hoy porque no hay superficie `api/*`; riesgo si se agrega una API pública sin restringir CORS explícitamente. |
| SEC-16 | Mass assignment | 100% de los ~50 modelos usan `$fillable` (whitelist); cero uso de `$guarded` | Muestra: `Estudiante.php:41`, `Matricula.php:42`, `Pago.php:44`, `User.php:35` | CUMPLE | — | Correcto en toda la muestra revisada. |
| SEC-17 | Validación de entradas | `$this-&gt;validate()` confirmado antes de cada persistencia en los componentes revisados de Matrícula, Pagos, Usuarios, Certificados | `matricula/wizard.blade.php` (7 validaciones), `pagos/index.blade.php`, `usuarios/ficha-modal.blade.php`, `certificados/index.blade.php` | CUMPLE | — | Patrón consistente. |
| SEC-18 | Validación de tipo de archivo subido | Matrícula sí valida (`mimes:pdf,jpg,jpeg,png`); Aula Virtual (materiales, grabaciones, entregas de tareas) **no** restringe extensión | `matricula/wizard.blade.php:166-169` (correcto) vs `aula-virtual/show.blade.php:148,186`; `tarea.blade.php:66` (sin `mimes:`) | CUMPLE PARCIALMENTE | Alto | Ver hallazgo crítico H-2. |
| SEC-19 | Manejo de errores sin exponer información sensible | `catch (Throwable $e)` en importación masiva expone `$e-&gt;getMessage()` crudo a la UI | `app/Modules/Matricula/Services/MatriculaService.php:299,345,406-412`; `app/Modules/Evaluaciones/Services/EvaluacionService.php:216-217` | CUMPLE PARCIALMENTE | Bajo-Medio | Impacto acotado (requiere ser staff autenticado con permiso de importación). |
| SEC-20 | Logs sin información sensible en texto plano | `NullWhatsAppGateway` registra teléfono + contenido completo del mensaje en `Log::info` | `app/Modules/Notificaciones/Gateways/NullWhatsAppGateway.php:20-23` | CUMPLE PARCIALMENTE | Bajo (informativo) | Es el driver de demo/desarrollo (`WHATSAPP_DRIVER=null` por defecto); riesgoso solo si quedara activo por descuido en producción. Ningún log registra contraseñas, tokens o números de tarjeta. |
| SEC-21 | Autorización por rol en rutas administrativas | Todas las rutas de administración (`usuarios`, `roles`, `auditoria`, `historial-contrasenas`, `academico.*`, `matricula.*`) tienen `auth` + `can:permiso` a nivel de ruta | `app/Modules/Identidad/Routes/web.php:9-27`; `app/Modules/Academico/Routes/web.php`; `app/Modules/Matricula/Routes/web.php:10-25` | CUMPLE | — | Sin rutas administrativas desprotegidas detectadas. |
| SEC-22 | Autorización por permiso en componentes Volt | 40 de 44 componentes con `mount()` invocan `Gate::authorize()`/`abort_unless(...hasPermissionTo...)` | Muestra de 10+ archivos en sección 10 | CUMPLE | — | Los 4 sin chequeo explícito son páginas públicas, pre-login o de perfil propio (justificado). |
| SEC-23 | Autorización a nivel de recurso (no solo de acción — IDOR) | Policies con scoping real por `docente_id`/matrícula en Asistencia, Evaluaciones, AulaVirtual, Pagos, Incidencias | `app/Modules/Asistencia/Policies/HorarioAsistenciaPolicy.php:26,47-63`; `app/Modules/Pagos/Policies/PagoPolicy.php:12-23` | CUMPLE | — | Buen patrón de defensa en profundidad, verificado con evidencia real de query scoping. |
| SEC-24 | IDOR puntual — solicitud de certificados | `matriculaId` recibido del cliente vía `wire:model` sin verificar que pertenezca al estudiante autenticado antes de `findOrFail` | `resources/views/livewire/certificados/mis-certificados.blade.php:51,60` | CUMPLE PARCIALMENTE | Medio | El `estudiante_id` de la solicitud sí se fija correctamente al usuario autenticado; el riesgo es que `matricula_id` podría corresponder a otro estudiante si se manipula la petición. |
| SEC-25 | Dependencias de seguridad actualizadas | `laravel/framework` v12.64.0, `livewire` v3.8.2, `spatie/laravel-permission` v6.25.0 — todas versiones recientes y mantenidas | `composer.lock` | CUMPLE | — | Sin auditoría de CVEs online realizada (fuera del alcance indicado); sin `composer audit`/Dependabot en CI (ver T-2 en sección 12). |
| SEC-26 | Rate limiting en acciones sensibles más allá del login | Envío masivo de WhatsApp y exportación de reportes protegidos solo por permiso, sin `throttle` adicional | `notificaciones/index.blade.php:74`; `reportes/index.blade.php:62,67,72` | CUMPLE PARCIALMENTE | Bajo | Riesgo acotado por requerir autenticación + permiso de staff. |

### 5.7 Mantenibilidad (Maintainability)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| MANT-1 | Modularidad | 13 módulos de dominio con estructura interna similar, pero acoplamiento cruzado significativo (`Incidencias` importa de 5 módulos distintos) | `app/Modules/Incidencias/Services/IncidenciaService.php:8-19` | CUMPLE PARCIALMENTE | Bajo-Medio | Acoplamiento mayormente justificado por el dominio, pero denso. |
| MANT-2 | Reusabilidad | Patrón de generación de PDF (`Pdf::loadView()-&gt;output()` + `addMediaFromString()`) duplicado en 4 módulos sin helper compartido | `CertificadoService.php:342,354`; `LibretaService.php:83,95`; `ReciboService.php:24,26`; `GenerarConstanciaVacantePdfJob.php:25,28` | CUMPLE PARCIALMENTE | Bajo | Repository pattern también aplicado solo en 4 de 13 módulos, de forma inconsistente. |
| MANT-3 | Analizabilidad | Larastan (Larastan/PHPStan) nivel 5 activo, baseline de solo 1 error ignorado; Pint configurado con preset Laravel | `phpstan.neon`; `phpstan-baseline.neon` (7 líneas) | CUMPLE | — | Deuda técnica silenciada mínima. Documento de arquitectura referenciado en comentarios pero ausente del repo resta puntos (ver DOC-2). |
| MANT-4 | Capacidad de modificación | Reglas de negocio (edad de mayoría 18, duración de ciclo 6/8 meses, dominio `@ceba.test`) hardcodeadas en Services/Value Objects en vez de `config/` | `MatriculaService.php:60,149`; `app/Shared/ValueObjects/Dni.php:57` | CUMPLE PARCIALMENTE | Bajo-Medio | Cambiar estas reglas exige un despliegue de código, no una config runtime. |
| MANT-5 | Manejo centralizado de excepciones | `bootstrap/app.php` con bloque `withExceptions` vacío; sin carpeta `app/Exceptions` ni excepciones de dominio propias | `bootstrap/app.php:16-18` | CUMPLE PARCIALMENTE | Bajo | El manejo de errores de negocio es consistente en la práctica (`ValidationException::withMessages`, 34 ocurrencias) pero por convención, no por infraestructura. |
| MANT-6 | Capacidad de ser probado (testability) | 73 archivos de test de Feature, 13/13 módulos cubiertos, CI en cada push; `RefreshDatabase` importado por archivo (no global vía `Pest.php`, comentado) | `tests/Feature/*`; `tests/Pest.php:16-18` | CUMPLE PARCIALMENTE | Bajo | Cobertura de feature/integración robusta; sin tests unitarios puros reales ni E2E (Dusk/Playwright/Cypress no presentes). |

### 5.8 Portabilidad (Portability)

| ID | Requisito | Evidencia encontrada | Ubicación | Estado | Severidad | Observaciones |
|---|---|---|---|---|---|---|
| PORT-1 | Adaptabilidad | Sin rutas hardcodeadas de Windows/XAMPP en `app/`/`config/`; configuración vía `.env`/`config()` en todo el código de negocio (0 usos de `env()` fuera de `config/`) | — | CUMPLE | — | Buena práctica consistente. |
| PORT-2 | Capacidad de instalación | `docs/DESPLIEGUE.md` (222 líneas) es detallado, específico y verificado como real (cita clases reales del proyecto); `README.md` es el placeholder genérico de Laravel sin personalizar, sin instrucciones de instalación local | `docs/DESPLIEGUE.md`; `README.md` (60 líneas, scaffold sin modificar) | CUMPLE PARCIALMENTE | Bajo-Medio | Fuerte para producción (Hostinger), débil para onboarding de un nuevo desarrollador local. |
| PORT-3 | Capacidad de sustitución | — | — | NO VERIFICABLE | — | Evaluar si el sistema puede sustituirse/ser sustituido sin impacto requiere un análisis fuera del alcance de una auditoría estática de un solo sistema. |

---

## 6. Hallazgos críticos

### [H-1] Cuenta desactivada conserva sesiones activas y puede volver a iniciar sesión

**Severidad:** CRÍTICO

**Requisito relacionado:** SEC-8 (Seguridad — Autenticidad/Responsabilidad); ISO 25010 — Seguridad, subcaracterísticas *Autenticidad* y *Responsabilidad*.

**Problema:** `app/Models/User.php:75-78` define `estaActivo(): bool { return $this-&gt;estado === EstadoUsuarioEnum::ACTIVO; }`, pero una búsqueda exhaustiva (`grep -rn "estaActivo"` en todo el proyecto) confirma que este método **nunca se invoca** fuera de su propia definición. `app/Livewire/Forms/LoginForm.php` línea 38 hace `Auth::attempt($this-&gt;only(['email','password']), ...)` sin ningún filtro adicional por `estado`. `app/Modules/Identidad/Services/UserManagementService.php` método `actualizar()` (líneas 47-56) solo actualiza la columna `estado` en la base de datos, sin invocar `SessionControlService` para revocar las sesiones activas de ese usuario.

**Evidencia:**
- `app/Models/User.php:75-78`
- `app/Livewire/Forms/LoginForm.php:38`
- `app/Modules/Identidad/Services/UserManagementService.php:47-56`
- `bootstrap/app.php:13-14` (bloque de middleware vacío; no hay middleware que verifique `estado` en cada request autenticado)

**Riesgo:** Si Dirección/Administrativo desactiva a un docente, tesorero o cualquier usuario (por ejemplo, tras un cese laboral o una sospecha de compromiso de cuenta), ese usuario: (a) puede seguir usando cualquier sesión que ya tenga abierta indefinidamente, sin ningún límite de tiempo más allá de `SESSION_LIFETIME`; y (b) según la evidencia estática, nada en el flujo de `Auth::attempt()` le impediría iniciar una **nueva** sesión con sus credenciales aún válidas. Esto anula el propósito práctico del campo "estado" del usuario como control de acceso, y es especialmente grave en un sistema que maneja datos financieros (Pagos) y académicos de menores de edad.

**Recomendación:** (1) Añadir un filtro `estado = activo` dentro de `LoginForm::authenticate()` antes o junto a `Auth::attempt()`, devolviendo un mensaje de error claro si la cuenta está inactiva. (2) Registrar un middleware global (o en el grupo `web`) que verifique `estaActivo()` en cada request autenticado y fuerce logout si no lo está. (3) Modificar `UserManagementService::actualizar()` para que, cuando el nuevo `estado` no sea `ACTIVO`, invoque `SessionControlService` y revoque todas las sesiones de ese usuario inmediatamente.

---

### [H-2] Subida de archivos en Aula Virtual sin validación de tipo (`mimes:`)

**Severidad:** ALTO

**Requisito relacionado:** SEC-18 (Seguridad — Confidencialidad/Integridad); ISO 25010 — Seguridad.

**Problema:** A diferencia del wizard de Matrícula (que sí valida `mimes:pdf,jpg,jpeg,png`), tres formularios de Aula Virtual solo validan que el campo sea un archivo dentro de un límite de tamaño, sin restringir la extensión:
- Material de clase: `'materialArchivo' =&gt; 'nullable|file|max:10240'` (`resources/views/livewire/aula-virtual/show.blade.php:148`)
- Grabación de clase: `'grabacionArchivo' =&gt; 'nullable|file|max:40000'` (línea 186)
- Entrega de tarea de un estudiante: `'archivo' =&gt; 'nullable|file|max:10240'` (`resources/views/livewire/aula-virtual/tarea.blade.php:66`)

Los archivos se almacenan en el disco `public` (`storage/app/public`), servido directamente vía el symlink `public/storage`, sin `.htaccess` que restrinja la ejecución de scripts en esa ruta.

**Evidencia:**
- `resources/views/livewire/aula-virtual/show.blade.php:148,186`
- `resources/views/livewire/aula-virtual/tarea.blade.php:66`
- `app/Modules/AulaVirtual/Services/MaterialService.php:59-72` (tampoco añade validación de tipo)
- `config/filesystems.php:41-45` (disco público servido directamente)

**Riesgo:** Un docente o un estudiante autenticado podría subir un archivo con extensión `.php` (u otra ejecutable) disfrazado de "material de clase" o "entrega de tarea". Si el servidor de producción ejecuta PHP en cualquier ruta bajo el docroot público (comportamiento típico de Apache sin configuración adicional de `<Directory>`/`.htaccess`), ese archivo podría quedar accesible y ejecutable vía URL pública. Esto no fue explotado ni confirmado en un entorno real como parte de esta auditoría (de solo lectura, sin pruebas activas), pero la ausencia de la validación es un hecho verificado en el código.

**Recomendación:** Añadir `mimes:` explícito a los tres campos (por ejemplo, `mimes:pdf,doc,docx,ppt,pptx,zip` para materiales, `mimes:mp4,mov` para grabaciones, y una lista razonable de tipos de entrega para tareas), y considerar además verificar el tipo MIME real del contenido (no solo la extensión) para archivos de mayor riesgo.

---

### [H-3] Emisión de certificados: condición de carrera en la numeración + operación multi-tabla sin transacción

**Severidad:** ALTO

**Requisito relacionado:** FS-2, FIA-3 (Fiabilidad — Tolerancia a fallos); ISO 25010 — Fiabilidad, Adecuación funcional.

**Problema:** `app/Modules/Certificados/Services/CertificadoService.php`:
- Método `emitir()` (líneas 63-108) crea el registro `Certificado`, genera y adjunta el PDF (I/O de archivo), y si viene de una solicitud, actualiza `SolicitudCertificado` a `ATENDIDA` — **sin envolver la operación en `DB::transaction()`**, a diferencia del patrón ya establecido y usado correctamente en `MatriculaService`/`PlanPagoService`.
- Método `siguienteNumero()` (líneas 392-398) calcula el próximo número correlativo contando registros existentes (`Certificado::query()-&gt;where('numero','like',...)-&gt;count()`), sin `lockForUpdate()` ni constraint único atómico a nivel de base de datos.

**Evidencia:**
- `app/Modules/Certificados/Services/CertificadoService.php:63-108,392-398`

**Riesgo:** (a) Si `generarPdf()` falla después de crear el `Certificado` pero antes de actualizar la `SolicitudCertificado`, queda un certificado sin PDF adjunto y una solicitud que sigue apareciendo como pendiente — un estado inconsistente que requeriría intervención manual para detectar y corregir. (b) Bajo concurrencia real (dos solicitudes de certificado procesadas casi simultáneamente), el cálculo de `siguienteNumero()` sin bloqueo puede producir **dos certificados con el mismo número correlativo**, lo cual es un problema serio para un documento oficial que debe ser único.

**Recomendación:** Envolver `emitir()` en `DB::transaction()`. Para la numeración, usar `lockForUpdate()` dentro de la misma transacción, o mejor aún, mover la generación del correlativo a una constraint única a nivel de base de datos con manejo de reintento ante colisión.

---

### [H-4] Seeders de demostración sin guarda de entorno

**Severidad:** MEDIO-ALTO

**Requisito relacionado:** SEC-1 (Confidencialidad); base de datos, sección 9.

**Problema:** `database/seeders/DatabaseSeeder.php` (líneas 19-76) crea usuarios de demostración con contraseñas de factory (`password`) y correos `@ceba.test`, y siembra datos ficticios completos de matrícula/pagos vía `DemoRobustoSeeder`, sin ningún condicional `if (app()-&gt;environment('local'))` o equivalente que impida su ejecución accidental en producción.

**Evidencia:**
- `database/seeders/DatabaseSeeder.php:19-76`
- `database/seeders/DemoRobustoSeeder.php` (sin guarda de entorno)

**Riesgo:** Si `php artisan db:seed` se ejecutara por error en producción (por ejemplo, en un despliegue mal configurado o un comando copiado sin revisar), se crearían usuarios con credenciales conocidas públicamente (documentadas incluso en este mismo repositorio) mezclados con datos reales de estudiantes.

**Recomendación:** Añadir una guarda explícita al inicio de `DatabaseSeeder::run()` (o de cada seeder de demo) que aborte si `app()-&gt;environment('production')`, y documentar esta protección en `docs/DESPLIEGUE.md`.

---

### [H-5] Webhook de WhatsApp no valida la firma de origen (HMAC)

**Severidad:** MEDIO

**Requisito relacionado:** SEC-12 (Seguridad — Autenticidad).

**Problema:** `config/services.php:44` define `WHATSAPP_APP_SECRET`, pero `WhatsappWebhookController.php` (líneas 42-61) nunca lo usa para validar la cabecera `X-Hub-Signature-256` que Meta envía en cada callback.

**Evidencia:**
- `app/Modules/Notificaciones/Http/Controllers/WhatsappWebhookController.php:42-61`
- `config/services.php:44`

**Riesgo:** Un tercero que conozca (o adivine) la URL del webhook podría enviar payloads falsificados que el sistema procesaría como legítimos (por ejemplo, actualizaciones de estado de mensajes o "mensajes entrantes" falsos), ya que no hay verificación criptográfica de que la petición realmente provenga de Meta.

**Recomendación:** Implementar la validación de firma HMAC-SHA256 estándar de Meta usando `WHATSAPP_APP_SECRET`, rechazando con 401/403 cualquier petición cuya firma no coincida.

---

### [H-6] Excepciones sin controlar expuestas en la UI durante cargas masivas

**Severidad:** BAJO-MEDIO

**Requisito relacionado:** SEC-19.

**Problema:** `MatriculaService.php:406-412` y `EvaluacionService.php:216-217` capturan `Throwable` genérico y devuelven `$e-&gt;getMessage()` directamente a la interfaz durante importaciones masivas por Excel.

**Evidencia:**
- `app/Modules/Matricula/Services/MatriculaService.php:299,345,406-412`
- `app/Modules/Evaluaciones/Services/EvaluacionService.php:216-217`

**Riesgo:** Si ocurre una excepción no anticipada (por ejemplo, un `QueryException` de MySQL), el mensaje crudo — que puede incluir nombres de columnas, tablas o detalles del esquema — se muestra al personal que hace la carga. Impacto acotado porque requiere ser staff autenticado con permiso de importación, pero es una mala práctica de manejo de errores.

**Recomendación:** Capturar tipos de excepción específicos y traducir a mensajes de negocio; para excepciones inesperadas, registrar el detalle completo en logs (`Log::error`) y mostrar al usuario un mensaje genérico ("No se pudo procesar la fila N, contacta a soporte").

---

## 7. Hallazgos de seguridad

Ver matriz completa en la sección 5.6 (SEC-1 a SEC-26). Resumen por severidad:

| Severidad | Hallazgos |
|---|---|
| CRÍTICO | H-1 (invalidación de sesión al desactivar cuenta) |
| ALTO | H-2 (subida de archivos sin `mimes:` en Aula Virtual) |
| MEDIO | H-5 (webhook sin HMAC), SEC-9 (sesión no se invalida al cambiar contraseña), SEC-10 (sin registro de intentos fallidos), SEC-24 (IDOR de integridad en solicitud de certificados) |
| BAJO | SEC-2 (DNI sin cifrar), SEC-15 (CORS wildcard latente), SEC-20 (log de WhatsApp con PII en driver null), SEC-26 (sin rate limiting en envío masivo/exportación) |
| BAJO-MEDIO | H-6 (excepciones crudas en importación masiva) |

**Aspectos de seguridad que SÍ cumplen sólidamente** (evidencia detallada en SEC-1, 3-7, 11, 13-14, 16-17, 21-23, 25): sin secretos hardcodeados, `.env` correctamente excluido de git, CSRF activo, sin XSS ni SQL injection detectables, 100% mass assignment protegido (`$fillable`), autenticación con 2FA TOTP real y rate limiting funcional, autorización consistente en rutas y componentes (incluyendo scoping real por recurso en Asistencia/Evaluaciones/AulaVirtual/Pagos), y dependencias de seguridad actualizadas.

---

## 8. Hallazgos arquitectónicos

- **Lógica de negocio concentrada en un componente Livewire en vez de un Service**: `resources/views/livewire/dashboard/index.blade.php` (836 líneas) ejecuta directamente 23+ queries y 6 métodos de cálculo de negocio sobre modelos de 7 módulos distintos, sin un `DashboardService` que centralice esa agregación. Contrasta con el resto del sistema, donde la norma es delegar a Services (confirmado en Matrícula, Pagos, Asistencia, Reportes).
- **Repository pattern aplicado solo en 4 de 13 módulos** (Academico, AulaVirtual, Identidad, Matricula), y de forma incompleta incluso ahí (ej. `Aula`, `Grado`, `PeriodoMatricula` en Academico no tienen repositorio).
- **N+1 queries confirmados** en `dashboard/index.blade.php:375-422` y `LibretaService.php:55-64` (ver PE-1).
- **Falta de paginación** en listados de Pagos, Certificados y Constancias (ver PE-2).
- **Reglas de negocio hardcodeadas** en vez de configuración: edad de mayoría (18), duración de ciclo de estudios (6 u 8 meses), dominio de correo institucional `@ceba.test` (ver MANT-4).
- **Sin manejo centralizado de excepciones**: `bootstrap/app.php` con `withExceptions` vacío, sin `app/Exceptions` (ver MANT-5). El comportamiento observado es consistente en la práctica, pero depende de la disciplina de cada desarrollador, no de la infraestructura.
- **Duplicación de lógica de generación de PDF** en 4 módulos sin helper compartido (ver MANT-2).
- **Acoplamiento cruzado alto pero mayormente justificado**: `Incidencias` importa de 5 módulos distintos; `Reportes` conoce el esquema interno de 6 módulos — razonable para módulos agregadores/transversales, pero sin abstracción que lo amortigüe.
- **Puntos positivos**: `DB::transaction()` bien usado en 11 archivos de Services (Matrícula, Pagos y otros), sin rutas hardcodeadas de Windows/XAMPP, convenciones de nombres consistentes (español para dominio, inglés para términos técnicos de Laravel).

---

## 9. Hallazgos de base de datos

- **Integridad referencial ejemplar**: 87 de 87 foreign keys revisadas tienen política de borrado explícita (`cascadeOnDelete`/`restrictOnDelete`/`nullOnDelete`), sin excepciones detectadas.
- **Claves primarias e índices correctos**: 50/50 tablas de módulos con `$table-&gt;id()`; índices únicos bien dirigidos en `dni`, `email`, y pares compuestos relevantes (`['estudiante_id','ciclo_id']`, `['evaluacion_id','estudiante_id']`, etc.).
- **Inconsistencia en soft deletes**: solo `Matricula` y `Estudiante` usan `SoftDeletes`; `Pago` y `Certificado` no lo tienen — si se implementara borrado para esos modelos en el futuro, sería un hard delete físico sin capa de recuperación, a diferencia del resto.
- **`estudiantes.email` sin `unique()`/`not null`**, a diferencia de `users.email` que sí los tiene.
- **DNI sin cifrado a nivel de columna** (ver SEC-2).
- **Seeders sin guarda de entorno** (ver H-4).
- **Backup solo a disco local del mismo servidor**, sin destino externo/redundante, y con notificación de fallo configurada a un email placeholder sin personalizar (`your@example.com`) — si el backup empieza a fallar, nadie se entera.
- **Trazabilidad de cambios sólida**: tabla `audit_logs` genérica aplicada vía trait `Auditable` en 48/50 modelos, con índice compuesto de rendimiento; complementada por una tabla dedicada de flujo de aprobación para cambios de monto de conceptos de pago (`solicitudes_cambio_monto`).
- **Migraciones bien construidas**: 0 métodos `down()` vacíos detectados en 87 migraciones revisadas; migraciones de datos (no solo de esquema) documentan explícitamente por qué su rollback es seguro.

---

## 10. Hallazgos de autenticación y autorización

- **Modelo de permisos**: Spatie Permission con permisos tipo `modulo.accion`, 6 roles institucionales. Dirección recibe un wildcard `'*'` que se traduce (no es un wildcard nativo de Spatie) en sincronizar la lista completa `TODOS_LOS_PERMISOS` — riesgo de diseño aceptado y explícitamente mitigado en el propio código de negocio (`asistencia/index.blade.php:50-52` comenta por qué no basta con `hasPermissionTo()` para distinguir "Dirección con el permiso" de "ser realmente docente").
- **Rutas administrativas**: todas protegidas con `auth` + `can:permiso` a nivel de ruta (Identidad, Academico, Matricula). El resto de módulos protege a nivel de `mount()` del componente Volt — verificado que el 100% de los casos revisados sí implementa el chequeo correspondiente, pero es un patrón que depende de la disciplina del desarrollador, no forzado por el framework.
- **Autorización a nivel de recurso (no solo de acción)**: confirmado con evidencia real de *scoping* por `docente_id`/matrícula en Policies de Asistencia, Evaluaciones, AulaVirtual, Pagos e Incidencias — no es solo "tiene el permiso, ve todo".
- **Gestión de sesiones**: `SessionControlService` permite ver y revocar sesiones activas (propias o, con permiso, de terceros), pero sin límite de sesiones concurrentes y sin invalidación automática tras desactivar una cuenta (ver H-1) o cambiar la contraseña propia (ver SEC-9).
- **2FA**: implementación TOTP real, opcional por autoservicio, con códigos de recuperación cifrados de un solo uso.
- **"Historial de Contraseñas"**: es un log informativo derivado de la auditoría genérica (marca *cuándo* cambió la contraseña), **no** un mecanismo que impida reutilizar contraseñas anteriores — no hay comparación de hashes históricos en el flujo de cambio de contraseña.
- **Registro de intentos fallidos de login**: solo existe el `RateLimiter` transitorio; no hay persistencia estructurada de esos eventos (ver SEC-10).

---

## 11. Trazabilidad y auditoría

Se evaluó si el sistema puede responder: *¿quién hizo qué, cuándo, sobre qué recurso, con qué resultado?*

| Pregunta | Respuesta | Evidencia |
|---|---|---|
| ¿Quién realizó una acción? | Sí — `user_id` capturado vía `Auth::id()` | `app/Modules/Identidad/Support/Auditable.php:39-51` |
| ¿Qué acción realizó? | Sí — evento `created`/`updated`/`deleted` | `Auditable.php:20-32` |
| ¿Cuándo la realizó? | Sí — `created_at` en `audit_logs` | Migración `2026_07_30_150000_create_audit_logs_table.php` |
| ¿Sobre qué recurso? | Sí — `auditable_type` + `auditable_id` (polimórfico) | Ídem |
| ¿Cuál fue el resultado (valores antes/después)? | Sí — `old_values`/`new_values` en JSON, con campos sensibles reemplazados por `'[oculto]'` | `Auditable.php:63-72` |
| ¿Desde dónde (IP/dispositivo)? | Sí — `ip`, `user_agent` | `Auditable.php:39-51` |
| ¿Cobertura de modelos? | 48 de 50 modelos de negocio (excepciones justificadas: `AuditLog` mismo, y `SolicitudContacto` público) | `grep -rn "use Auditable"` |
| ¿Ejecución sin bloquear la petición? | Sí — vía `RegistrarAuditoriaJob` (cola dedicada `auditoria`) | `app/Modules/Identidad/Jobs/RegistrarAuditoriaJob.php:18,36` |
| ¿Intentos de acceso fallidos (login)? | **No hay persistencia**, solo rate limiting transitorio | Ver SEC-10 |
| ¿Consulta de la auditoría por un administrador? | Sí — UI dedicada con filtros por evento/tipo de modelo | `resources/views/livewire/auditoria/index.blade.php` |

**Conclusión de esta sección**: la trazabilidad de *cambios en datos* es una de las capacidades mejor implementadas del sistema. La brecha real está en la trazabilidad de *intentos de acceso* (login fallido) y en la falta de invalidación de sesión ante cambios de estado de la cuenta (H-1), que rompe la cadena de responsabilidad de "quién tuvo acceso durante cuánto tiempo".

---

## 12. Pruebas

- **73 archivos** en `tests/Feature`, cubriendo el **100% (13/13)** de los módulos de `app/Modules`, más `tests/Feature/Auth` y tests raíz de regresión (`ModuleRoutesHaveWebMiddlewareTest`, `HeaderActionButtonsAreInteractiveTest`).
- **13 archivos `*PermisosTest.php`** dedicados específicamente a verificar autorización por rol — una práctica de pruebas de seguridad explícita y deliberada, no incidental.
- Cobertura confirmada de casos de error (no solo happy path): 20+ archivos con `assertForbidden`/`assertStatus(403)`/`assertInvalid`, incluyendo pruebas de reglas de negocio complejas (traslape de horarios, validación de ciclos, bloqueo por deuda).
- **T-1 (Informativo/Bajo):** sin tests unitarios puros reales (`tests/Unit` solo tiene el placeholder de Laravel) ni tests end-to-end (Dusk/Playwright/Cypress no están en `composer.json`/`package.json`). La suite es enteramente de feature/integración con Pest + `RefreshDatabase` sobre SQLite en memoria.
- **T-2 (Medio):** el pipeline de CI (`.github/workflows/ci.yml`) ejecuta Pint + Larastan + Pest en cada push/PR a `main`, pero **no incluye** `composer audit`, `npm audit` ni Dependabot — no hay verificación automática de vulnerabilidades conocidas en dependencias.
- **T-3 (Bajo):** `RefreshDatabase` no se aplica globalmente desde `tests/Pest.php` (la línea que lo haría está comentada); en su lugar, cada una de las 73 clases de test lo importa individualmente. Funciona igual en la práctica, pero es un patrón más frágil ante el olvido en un test nuevo.
- Larastan nivel 5 con un baseline de una sola entrada ignorada — deuda técnica de tipado prácticamente nula.

**Veredicto**: la estrategia de pruebas es notablemente más rigurosa que el promedio de proyectos de este tamaño (cobertura sistemática de autorización, casos de error, CI real), pero **"existen tests" no equivale a "todo el sistema está probado"**: los hallazgos H-3 (condición de carrera en certificados) y H-1 (sesión no invalidada) no están cubiertos por ningún test, es decir, la suite no verifica estos escenarios de concurrencia ni de seguridad de sesión.

---

## 13. Documentación

| Documento | Evaluación |
|---|---|
| `README.md` | **Deficiente** — es el scaffold genérico de Laravel sin personalizar; no menciona CEBA, no tiene instrucciones de instalación local, apunta a `taylor@laravel.com` para reportes de seguridad (el creador de Laravel, no el proyecto). |
| `docs/DESPLIEGUE.md` | **Excelente** — 222 líneas, específico, verificado con clases y comandos reales del proyecto, cubre variables de entorno, migraciones, colas sin Supervisor (workaround real para hosting compartido), backups, monitoreo, y hasta una ruta de migración futura a VPS con Horizon. |
| Documento de arquitectura | **Ausente del repositorio**, pero referenciado explícitamente en al menos 2 comentarios de código (`resources/css/app.css:5`, `RolesAndPermissionsSeeder.php:15`) — probablemente vive fuera de git (Notion/Word/etc.), lo cual es un riesgo de "bus factor": el razonamiento detrás de decisiones de diseño (sistema de tokens de color, estructura de permisos) no es auditable desde el código. |
| Documentación de API | No aplica — el sistema no expone una API REST/pública (no hay `routes/api.php`). |
| Documentación de base de datos | No hay diagrama ER ni documento dedicado; el esquema debe inferirse de las migraciones. |

**Veredicto**: la documentación operativa (despliegue) es de calidad notablemente alta, pero la documentación de *onboarding* (README) y de *arquitectura* (ausente del repo) son puntos débiles reales que afectan la mantenibilidad a largo plazo, especialmente si el proyecto cambia de responsable.

---

## 14. Requisitos no verificables

Los siguientes puntos requieren evidencia externa al repositorio (acceso al servidor de producción real, pruebas de carga, estudios de usuario, o el texto oficial completo de ISO/IEC 25010 y 25023) y **no se marcan con un estado de cumplimiento definitivo**:

1. **Configuración real de producción** (`APP_DEBUG`, `SESSION_SECURE_COOKIE`, `APP_ENV`): el `.env` presente en el repositorio de trabajo es el de un entorno `local` (`APP_DEBUG=true`); no hay `.env` de producción en el repo (correctamente, por diseño), por lo que no es posible confirmar si la configuración desplegada real difiere de forma insegura.
2. **Disponibilidad (uptime) del sistema en producción** — requiere métricas de monitoreo reales, no verificables desde el código.
3. **Capacidad bajo carga real** (número de usuarios concurrentes soportados, tiempos de respuesta bajo estrés) — requiere pruebas de carga (JMeter, k6, etc.) contra un entorno desplegado.
4. **Coexistencia con otro software en el mismo servidor de producción** — depende de la infraestructura real de Hostinger, no del código.
5. **Usabilidad con usuarios reales** (capacidad de reconocer adecuación, capacidad de aprendizaje, estética de interfaz) — requiere estudios de UX/usabilidad con personal real del CEBA, fuera del alcance de una auditoría de código.
6. **Accesibilidad completa** (WCAG: contraste de color, navegación por teclado, compatibilidad con lectores de pantalla) — solo se verificó la presencia de `label for=`/`id=`; una auditoría de accesibilidad real requeriría herramientas dedicadas (axe-core, Lighthouse) contra la aplicación en ejecución.
7. **Capacidad de sustitución** (interoperabilidad de reemplazo con otro sistema) — análisis fuera del alcance de auditar un solo sistema de forma aislada.
8. **Cumplimiento normativo exacto contra el texto oficial de ISO/IEC 25010 y sus métricas de ISO/IEC 25023** — esta auditoría usa la taxonomía pública del modelo (8 características, 31 subcaracterísticas), no el documento pagado con numeración de cláusulas exacta; ver advertencia en la sección 3.

---

## 15. Riesgos

| Riesgo | Origen | Probabilidad estimada | Impacto | Prioridad |
|---|---|---|---|---|
| Acceso no autorizado sostenido tras desactivación de cuenta (empleado cesado, cuenta comprometida) | H-1 | Media (requiere que se desactive una cuenta activa) | Alto (acceso a datos académicos/financieros) | **Crítica** |
| Subida y posible ejecución de un archivo malicioso vía Aula Virtual | H-2 | Baja-Media (requiere intención maliciosa de un usuario ya autenticado) | Alto (compromiso del servidor) | **Alta** |
| Certificado oficial duplicado o inconsistente por condición de carrera | H-3 | Baja (requiere emisiones simultáneas reales) | Medio-Alto (documento oficial inválido) | **Alta** |
| Contaminación de producción con datos/usuarios de demo | H-4 | Baja (requiere error operativo en despliegue) | Alto (credenciales conocidas públicamente) | **Media-Alta** |
| Procesamiento de webhooks de WhatsApp falsificados | H-5 | Baja (requiere conocer la URL del endpoint) | Bajo-Medio (canal de envío real ya está desactivado) | **Media** |
| Filtración de detalles de esquema de BD vía mensajes de error | H-6 | Baja | Bajo | **Baja** |
| Pérdida de datos ante fallo del único servidor de backup | D-5 (sección 9) | Baja | Alto (sin backup de respaldo externo) | **Media-Alta** |

---

## 16. Plan de remediación

### Prioridad 1 — Crítica

**[P1-1] Forzar verificación de `estado` del usuario en el flujo de autenticación y sesión**
- Problema: H-1.
- Requisito relacionado: SEC-8.
- Acción recomendada: (a) añadir chequeo de `estaActivo()` dentro de `LoginForm::authenticate()`; (b) registrar un middleware que verifique `estado` en cada request autenticado; (c) invocar `SessionControlService::revocarTodas()` desde `UserManagementService::actualizar()` cuando el nuevo estado no sea `ACTIVO`.
- Archivos/componentes afectados: `app/Livewire/Forms/LoginForm.php`, `app/Modules/Identidad/Services/UserManagementService.php`, `bootstrap/app.php` (registro de middleware), `app/Http/Middleware/` (nuevo middleware).
- Complejidad estimada: Media (requiere una migración conceptual del flujo de auth, pero el `SessionControlService` ya existe).
- Dependencias: ninguna.
- Criterio de solución: un test de Feature que (1) desactiva un usuario, (2) confirma que su sesión existente ya no responde 200 en una ruta protegida, y (3) confirma que un nuevo intento de login con sus credenciales es rechazado.

### Prioridad 2 — Alta

**[P2-1] Validar tipo de archivo en formularios de Aula Virtual**
- Problema: H-2.
- Requisito relacionado: SEC-18.
- Acción: añadir `mimes:` explícito a los 3 campos de subida (`materialArchivo`, `grabacionArchivo`, entrega de tarea `archivo`).
- Archivos: `resources/views/livewire/aula-virtual/show.blade.php:148,186`; `resources/views/livewire/aula-virtual/tarea.blade.php:66`.
- Complejidad: Baja.
- Dependencias: ninguna.
- Criterio de solución: test de Feature que confirma que subir un archivo `.php` a cada uno de los 3 formularios es rechazado con error de validación.

**[P2-2] Envolver `CertificadoService::emitir()` en transacción y proteger la numeración correlativa**
- Problema: H-3.
- Requisito relacionado: FIA-3, FS-2.
- Acción: envolver el método en `DB::transaction()`; usar `lockForUpdate()` (o constraint único con reintento) en `siguienteNumero()`.
- Archivos: `app/Modules/Certificados/Services/CertificadoService.php:63-108,392-398`.
- Complejidad: Baja-Media.
- Dependencias: ninguna.
- Criterio de solución: test que simula un fallo a mitad del proceso y confirma que no queda un `Certificado` huérfano; test que simula concurrencia (dos llamadas casi simultáneas) y confirma números distintos.

**[P2-3] Añadir guarda de entorno a los seeders de demostración**
- Problema: H-4.
- Requisito relacionado: SEC-1.
- Acción: agregar `abort_if(app()-&gt;environment('production'), ...)` (o equivalente) al inicio de `DatabaseSeeder::run()`.
- Archivos: `database/seeders/DatabaseSeeder.php`.
- Complejidad: Muy baja.
- Dependencias: ninguna.
- Criterio de solución: ejecutar `php artisan db:seed` con `APP_ENV=production` falla explícitamente con un mensaje claro.

### Prioridad 3 — Media

**[P3-1] Validar firma HMAC del webhook de WhatsApp**
- Problema: H-5. Archivos: `app/Modules/Notificaciones/Http/Controllers/WhatsappWebhookController.php`.
- Complejidad: Baja. Criterio: peticiones con firma inválida devuelven 401/403.

**[P3-2] Verificar propiedad de `matriculaId` en solicitud de certificados**
- Problema: SEC-24. Archivos: `resources/views/livewire/certificados/mis-certificados.blade.php:51,60`.
- Complejidad: Baja. Criterio: un estudiante no puede solicitar un certificado sobre una matrícula de otro estudiante (test de Feature).

**[P3-3] Invalidar otras sesiones al cambiar la propia contraseña**
- Problema: SEC-9. Archivos: `resources/views/livewire/profile/update-password-form.blade.php`.
- Complejidad: Baja (reutilizar `SessionControlService::revocarTodasMenosActual`).

**[P3-4] Configurar destino de backup externo y notificación real de fallo**
- Problema: sección 9 (D-5). Archivos: `config/backup.php`.
- Complejidad: Media (depende de contratar/configurar almacenamiento externo).

**[P3-5] Agregar `composer audit`/Dependabot al pipeline de CI**
- Problema: T-2. Archivos: `.github/workflows/ci.yml`, `.github/dependabot.yml` (nuevo).
- Complejidad: Baja.

**[P3-6] Registrar intentos fallidos de login de forma persistente**
- Problema: SEC-10. Archivos: nuevo listener para `Illuminate\Auth\Events\Failed`/`Lockout`.
- Complejidad: Baja-Media.

**[P3-7] Personalizar el README y versionar el documento de arquitectura**
- Problema: sección 13. Archivos: `README.md`; nuevo `docs/ARQUITECTURA.md`.
- Complejidad: Baja (esfuerzo de redacción, no técnico).

### Prioridad 4 — Baja

**[P4-1]** Extraer un helper compartido para generación de PDF (`app/Shared`), eliminando la duplicación en 4 módulos.
**[P4-2]** Mover reglas de negocio hardcodeadas (edad legal, duración de ciclo) a `config/` o base de datos.
**[P4-3]** Añadir manejo de excepciones específico (no `Throwable` genérico) en importaciones masivas, sin exponer `$e-&gt;getMessage()` crudo.
**[P4-4]** Añadir paginación a los listados de Pagos, Certificados y Constancias.
**[P4-5]** Resolver los N+1 confirmados en `dashboard/index.blade.php` y `LibretaService.php`.
**[P4-6]** Extraer un `DashboardService` para sacar la lógica de negocio del componente Livewire de 836 líneas.
**[P4-7]** Eliminar la dependencia huérfana `@tailwindcss/vite` de `package.json` (el proyecto usa Tailwind v3, no v4).
**[P4-8]** Añadir `wire:loading` a los flujos críticos que carecen de él (login, wizard de matrícula).
**[P4-9]** Evaluar cifrado a nivel de columna para `dni` en `users`/`estudiantes`.
**[P4-10]** Limitar sesiones concurrentes por usuario (opcional, según apetito de riesgo institucional).

---

## 17. Checklist final

- [x] Sin secretos/credenciales hardcodeadas en el código — `SEC-1`
- [x] `.env` correctamente excluido de git — verificado directamente
- [x] CSRF activo sin excepciones injustificadas — `SEC-11`
- [x] Sin XSS explotable detectado — `SEC-13`
- [x] Sin SQL Injection detectado — `SEC-14`
- [x] 100% de modelos con `$fillable` (mass assignment protegido) — `SEC-16`
- [x] Validación de entradas consistente antes de persistir — `SEC-17`
- [x] Integridad referencial completa (87/87 FKs con política de borrado) — `SEC-3`
- [x] Autenticación con 2FA real y rate limiting — `SEC-6`, `SEC-7`
- [x] Autorización por rol en rutas administrativas — `SEC-21`
- [x] Autorización a nivel de recurso (no solo de acción) — `SEC-23`
- [x] Trazabilidad de cambios en datos (auditoría en 48/50 modelos) — `SEC-4`
- [x] Suite de tests cubriendo 100% de los módulos, con foco explícito en permisos — sección 12
- [x] CI real ejecutando lint + análisis estático + tests en cada push — sección 12
- [x] Documentación de despliegue completa y verificada — sección 13
- [ ] [CRÍTICO] Cuenta desactivada invalida sus sesiones y bloquea nuevos logins — `H-1`, no cumplido
- [ ] [ALTO] Validación de tipo de archivo en todos los formularios de subida — `H-2`, parcial (Matrícula sí, Aula Virtual no)
- [ ] [ALTO] Operaciones multi-tabla críticas siempre en transacción — `H-3`, parcial (Certificados es la excepción)
- [ ] [MEDIO-ALTO] Seeders de demo con guarda de entorno — `H-4`, no cumplido
- [ ] [MEDIO] Webhook externo con validación de firma — `H-5`, no cumplido
- [ ] [MEDIO] Registro persistente de intentos fallidos de login — `SEC-10`, no cumplido
- [ ] [BAJO-MEDIO] Documento de arquitectura versionado en el repositorio — sección 13, ausente
- [ ] [BAJO] README personalizado con instrucciones de instalación local — sección 13, pendiente

---

## 18. Conclusión

### ¿El proyecto cumple con la norma?

**Parcialmente.**

CEBA demuestra una base de ingeniería sólida en varias de las ocho características del modelo ISO/IEC 25010: **integridad referencial y protección contra mass assignment prácticamente perfectas**, **ausencia de XSS/SQLi/CSRF explotables**, **autenticación robusta con 2FA real**, **autorización consistente y con scoping real a nivel de recurso** (no solo de acción/rol), y **un sistema de auditoría/trazabilidad aplicado sistemáticamente a 48 de 50 modelos de negocio**. La suite de pruebas (73 archivos, 100% de módulos cubiertos, con 13 archivos dedicados exclusivamente a verificar permisos) y el pipeline de CI real refuerzan esa base.

Sin embargo, el cumplimiento no es completo por hallazgos concretos y verificables, el más severo de los cuales es que **desactivar la cuenta de un usuario no revoca sus sesiones activas ni impide que vuelva a iniciar sesión** (`H-1`) — esto contradice directamente el propósito de la subcaracterística de *Autenticidad* y *Responsabilidad* dentro de la característica de Seguridad de ISO/IEC 25010, y es el tipo de brecha que en una auditoría formal normalmente bloquea una certificación hasta ser corregida. A esto se suman una vulnerabilidad de validación de archivos en Aula Virtual (`H-2`), una condición de carrera real en la emisión de certificados oficiales (`H-3`), y seeders de demostración sin protección de entorno (`H-4`).

**Principales puntos que deben corregirse para alcanzar un nivel de cumplimiento BUENO:**

1. Corregir `H-1` (invalidación de sesión al desactivar cuenta) — es el bloqueador principal.
2. Corregir `H-2` (validación de archivos en Aula Virtual) y `H-3` (transacción + numeración de certificados).
3. Añadir la guarda de entorno a los seeders (`H-4`).
4. Cerrar las brechas medias de autorización/trazabilidad: firma HMAC del webhook (`H-5`), registro persistente de intentos fallidos de login, e invalidación de sesión al cambiar la propia contraseña.
5. Formalizar la documentación de arquitectura (hoy referenciada en comentarios de código pero ausente del repositorio) y personalizar el README para facilitar el onboarding y la continuidad del proyecto.

Ninguno de estos puntos requiere un rediseño del sistema — todos son correcciones acotadas y verificables sobre una base arquitectónica que, en términos generales, ya sigue buenas prácticas de Laravel de forma consistente.
