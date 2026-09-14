<?php

use App\Modules\Academico\Providers\AcademicoServiceProvider;
use App\Modules\Asistencia\Providers\AsistenciaServiceProvider;
use App\Modules\AulaVirtual\Providers\AulaVirtualServiceProvider;
use App\Modules\Certificados\Providers\CertificadosServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Docentes\Providers\DocentesServiceProvider;
use App\Modules\Evaluaciones\Providers\EvaluacionesServiceProvider;
use App\Modules\FlujoCaja\Providers\FlujoCajaServiceProvider;
use App\Modules\Identidad\Providers\IdentidadServiceProvider;
use App\Modules\Incidencias\Providers\IncidenciasServiceProvider;
use App\Modules\Landing\Providers\LandingServiceProvider;
use App\Modules\Matricula\Providers\MatriculaServiceProvider;
use App\Modules\Migraciones\Providers\MigracionesServiceProvider;
use App\Modules\Notificaciones\Providers\NotificacionesServiceProvider;
use App\Modules\Pagos\Providers\PagosServiceProvider;
use App\Modules\Personal\Providers\PersonalServiceProvider;
use App\Modules\Reportes\Providers\ReportesServiceProvider;
use App\Modules\Vacaciones\Providers\VacacionesServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    VoltServiceProvider::class,
    IdentidadServiceProvider::class,
    LandingServiceProvider::class,
    DashboardServiceProvider::class,
    AcademicoServiceProvider::class,
    MatriculaServiceProvider::class,
    AulaVirtualServiceProvider::class,
    AsistenciaServiceProvider::class,
    EvaluacionesServiceProvider::class,
    PagosServiceProvider::class,
    CertificadosServiceProvider::class,
    ReportesServiceProvider::class,
    NotificacionesServiceProvider::class,
    IncidenciasServiceProvider::class,
    MigracionesServiceProvider::class,
    VacacionesServiceProvider::class,
    FlujoCajaServiceProvider::class,
    DocentesServiceProvider::class,
    PersonalServiceProvider::class,
];
