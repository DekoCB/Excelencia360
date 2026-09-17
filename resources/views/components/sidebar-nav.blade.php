<nav class="sidebar-nav flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
    <a
        href="{{ route('dashboard') }}"
        wire:navigate
        @class([
            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
            'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('dashboard'),
            'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('dashboard'),
        ])
    >
        <x-heroicon-o-squares-2x2 class="h-5 w-5 shrink-0" />
        <span class="sidebar-label">Dashboard</span>
    </a>

    @canany(['tramites.crear', 'tramites.ver_propio', 'tramites.gestionar'])
        <a
            href="{{ route('tramites.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('tramites.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('tramites.*'),
            ])
        >
            <x-heroicon-o-inbox-stack class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Trámites</span>
        </a>
    @endcan

    @can('calendario.ver')
        <a
            href="{{ route('calendario.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('calendario.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('calendario.*'),
            ])
        >
            <x-heroicon-o-calendar-days class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Calendario</span>
        </a>
    @endcan

    @canany(['matricula.ver_propio_hijo', 'reportes.historial_estudiante'])
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Portal de Apoderados
            </p>
        </div>

        <a
            href="{{ route('matricula.mis-hijos') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('matricula.mis-hijos'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('matricula.mis-hijos'),
            ])
        >
            <x-heroicon-o-user-group class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Tutores/Apoderados</span>
        </a>
    @endcanany

    @can('matricula.ver')
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Matrícula
            </p>
        </div>

        <a
            href="{{ route('matricula.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('matricula.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('matricula.*'),
            ])
        >
            <x-heroicon-o-identification class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Estudiantes</span>
        </a>

        @can('migraciones.ver')
            <a
                href="{{ route('migraciones.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('migraciones.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('migraciones.*'),
                ])
            >
                <x-heroicon-o-arrow-up-circle class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Migraciones</span>
            </a>
        @endcan

        @can('vacaciones.ver')
            <a
                href="{{ route('vacaciones.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('vacaciones.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('vacaciones.*'),
                ])
            >
                <x-heroicon-o-sun class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Vacaciones</span>
            </a>
        @endcan
    @endcan

    @canany(['aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio'])
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Aula Virtual
            </p>
        </div>

        <a
            href="{{ route('aula-virtual.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('aula-virtual.index', 'aula-virtual.show', 'aula-virtual.tarea'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('aula-virtual.index', 'aula-virtual.show', 'aula-virtual.tarea'),
            ])
        >
            <x-heroicon-o-computer-desktop class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Cursos virtuales</span>
        </a>
    @endcanany

    @canany(['asistencia.ver', 'asistencia.registrar', 'asistencia.ver_propio'])
        <a
            href="{{ route('asistencia.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('asistencia.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('asistencia.*'),
            ])
        >
            <x-heroicon-o-clipboard-document-check class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Asistencia</span>
        </a>
    @endcanany

    @canany(['asistencia_docentes.ver', 'asistencia_docentes.registrar', 'asistencia_docentes.ver_propio'])
        <a
            href="{{ route('asistencia-docentes.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('asistencia-docentes.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('asistencia-docentes.*'),
            ])
        >
            <x-heroicon-o-briefcase class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Asistencia docente</span>
        </a>
    @endcanany

    @canany(['biblioteca.ver', 'biblioteca.gestionar'])
        <a
            href="{{ route('biblioteca.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' => request()->routeIs('biblioteca.index'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('biblioteca.index'),
            ])
        >
            <x-heroicon-o-book-open class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Biblioteca</span>
        </a>
    @endcanany

    @can('biblioteca.ver_propio')
        <a
            href="{{ route('biblioteca.mis-prestamos') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' => request()->routeIs('biblioteca.mis-prestamos'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('biblioteca.mis-prestamos'),
            ])
        >
            <x-heroicon-o-clock class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Mis préstamos</span>
        </a>
    @endcan

    {{-- Evaluaciones vive dentro de Cursos Virtuales (ver aula-virtual.evaluacion) -- ya no tiene entrada propia acá. --}}
    @if (auth()->user()->can('evaluaciones.ver_propio') && auth()->user()->estudiante)
        <a
            href="{{ route('evaluaciones.mi-libreta') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('evaluaciones.mi-libreta'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('evaluaciones.mi-libreta'),
            ])
        >
            <x-heroicon-o-book-open class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Mi libreta</span>
        </a>
    @endif

    @canany(['incidencias.ver', 'incidencias.crear', 'incidencias.gestionar_propio', 'incidencias.ver_propio'])
        <a
            href="{{ route('incidencias.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('incidencias.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('incidencias.*'),
            ])
        >
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Incidencias</span>
        </a>
    @endcanany

    @canany(['pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar', 'pagos.ver_propio', 'tesoreria.gestionar'])
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Pagos
            </p>
        </div>

        @canany(['pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar'])
            <a
                href="{{ route('pagos.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('pagos.index'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.index'),
                ])
            >
                <x-heroicon-o-banknotes class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Cobranza</span>
            </a>
        @endcanany

        {{-- pagos.ver_propio también lo tiene Dirección vía '*', pero mi-cuenta
             exige además una ficha de Estudiante: sin ella el enlace 403ea. --}}
        @if (auth()->user()->can('pagos.ver_propio') && auth()->user()->estudiante)
            <a
                href="{{ route('pagos.mi-cuenta') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('pagos.mi-cuenta'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.mi-cuenta'),
                ])
            >
                <x-heroicon-o-credit-card class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Mi estado de cuenta</span>
            </a>
        @endif

        @can('pagos.gestionar')
            <a
                href="{{ route('pagos.conceptos') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('pagos.conceptos'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.conceptos'),
                ])
            >
                <x-heroicon-o-tag class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Conceptos de pago</span>
            </a>
        @endcan

        @can('tesoreria.gestionar')
            <a
                href="{{ route('pagos.cuentas-bancarias') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('pagos.cuentas-bancarias'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.cuentas-bancarias'),
                ])
            >
                <x-heroicon-o-building-library class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Cuentas bancarias</span>
            </a>
        @endcan

        @can('flujo_caja.ver')
            <a
                href="{{ route('flujo-caja.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('flujo-caja.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('flujo-caja.*'),
                ])
            >
                <x-heroicon-o-banknotes class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Flujo de caja</span>
            </a>
        @endcan
    @endcanany

    @canany(['certificados.ver', 'certificados.emitir', 'certificados.duplicar', 'certificados.solicitar'])
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Trámites
            </p>
        </div>

        @canany(['certificados.ver', 'certificados.emitir'])
            <a
                href="{{ route('certificados.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('certificados.index'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('certificados.index'),
                ])
            >
                <x-heroicon-o-document-check class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Certificados</span>
            </a>

            <a
                href="{{ route('constancias.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('constancias.index'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('constancias.index'),
                ])
            >
                <x-heroicon-o-clipboard-document class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Constancias</span>
            </a>
        @endcanany

        {{-- certificados.solicitar también lo tiene Dirección vía '*', pero
             mis-certificados/mis-constancias exigen además una ficha de
             Estudiante. --}}
        @if (auth()->user()->can('certificados.solicitar') && auth()->user()->estudiante)
            <a
                href="{{ route('certificados.mis-certificados') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('certificados.mis-certificados'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('certificados.mis-certificados'),
                ])
            >
                <x-heroicon-o-document-text class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Mis certificados</span>
            </a>

            <a
                href="{{ route('constancias.mis-constancias') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('constancias.mis-constancias'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('constancias.mis-constancias'),
                ])
            >
                <x-heroicon-o-clipboard-document-list class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Mis constancias</span>
            </a>
        @endif
    @endcanany

    @can('academico.ver')
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Académico
            </p>
        </div>

        @foreach ([
            ['route' => 'academico.siagie.index', 'prefix' => 'academico.siagie.*', 'label' => 'Periodo Académico', 'icon' => 'identification'],
            ['route' => 'academico.programas-estudio.index', 'prefix' => 'academico.programas-estudio.*', 'label' => 'Programa de Estudio', 'icon' => 'academic-cap'],
            ['route' => 'academico.grados.index', 'prefix' => 'academico.grados.*', 'label' => 'Semestres', 'icon' => 'bookmark'],
            ['route' => 'academico.cursos.index', 'prefix' => 'academico.cursos.*', 'label' => 'Cursos', 'icon' => 'book-open'],
            ['route' => 'academico.aulas.index', 'prefix' => 'academico.aulas.*', 'label' => 'Aulas', 'icon' => 'building-office-2'],
            ['route' => 'academico.horarios.index', 'prefix' => 'academico.horarios.*', 'label' => 'Horarios', 'icon' => 'clock'],
            ['route' => 'academico.ciclos.index', 'prefix' => 'academico.ciclos.*', 'label' => 'Período de Matrícula', 'icon' => 'arrow-path'],
        ] as $enlace)
            <a
                href="{{ route($enlace['route']) }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs($enlace['prefix']),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs($enlace['prefix']),
                ])
            >
                <x-dynamic-component :component="'heroicon-o-'.$enlace['icon']" class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">{{ $enlace['label'] }}</span>
            </a>
        @endforeach
    @endcan

    @canany(['reportes.academicos', 'reportes.matricula', 'reportes.financieros', 'reportes.certificados', 'reportes.operativos', 'reportes.propios'])
        <div class="mt-4 border-t border-border pt-4">
            <a
                href="{{ route('reportes.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('reportes.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('reportes.*'),
                ])
            >
                <x-heroicon-o-chart-bar class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Reportes</span>
            </a>
        </div>
    @endcanany

    @can('reportes.historial_estudiante')
        <a
            href="{{ route('historial-estudiante.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('historial-estudiante.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('historial-estudiante.*'),
            ])
        >
            <x-heroicon-o-document-magnifying-glass class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Historial de estudiante</span>
        </a>
    @endcan

    @canany(['whatsapp.ver', 'whatsapp.enviar'])
        <div class="mt-4 border-t border-border pt-4">
            <a
                href="{{ route('notificaciones.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('notificaciones.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('notificaciones.*'),
                ])
            >
                <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Notificaciones</span>
            </a>
        </div>
    @endcanany

    @if (auth()->user()->can('notificaciones.ver_propio') && auth()->user()->estudiante)
        <div class="mt-4 border-t border-border pt-4">
            <a
                href="{{ route('notificaciones.mis-mensajes') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('notificaciones.mis-mensajes'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('notificaciones.mis-mensajes'),
                ])
            >
                <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Mis mensajes</span>
            </a>
        </div>
    @endif

    @canany(['docentes.ver', 'contratos.ver', 'personal.ver'])
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Recursos Humanos
            </p>
        </div>

        @can('docentes.ver')
            <a
                href="{{ route('docentes.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('docentes.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('docentes.*'),
                ])
            >
                <x-heroicon-o-academic-cap class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Docentes</span>
            </a>
        @endcan

        @can('contratos.ver')
            <a
                href="{{ route('contratos.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('contratos.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('contratos.*'),
                ])
            >
                <x-heroicon-o-document-text class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Contratos</span>
            </a>
        @endcan

        @can('personal.ver')
            <a
                href="{{ route('personal.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('personal.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('personal.*'),
                ])
            >
                <x-heroicon-o-user-group class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Personal</span>
            </a>
        @endcan
    @endcanany

    @canany(['usuarios.ver', 'roles.gestionar', 'auditoria.ver'])
        <div class="mt-4 border-t border-border pt-4">
            <p class="sidebar-section-title px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                Administración
            </p>
        </div>

        @can('usuarios.ver')
            <a
                href="{{ route('usuarios.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('usuarios.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('usuarios.*'),
                ])
            >
                <x-heroicon-o-users class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Usuarios</span>
            </a>
        @endcan

        @can('roles.gestionar')
            <a
                href="{{ route('roles.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('roles.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('roles.*'),
                ])
            >
                <x-heroicon-o-shield-check class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Roles y permisos</span>
            </a>
        @endcan

        @can('auditoria.ver')
            <a
                href="{{ route('auditoria.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('auditoria.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('auditoria.*'),
                ])
            >
                <x-heroicon-o-clipboard-document-list class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Auditoría</span>
            </a>
        @endcan

        @can('auditoria.ver')
            <a
                href="{{ route('historial-contrasenas.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-md shadow-accent/30' =>request()->routeIs('historial-contrasenas.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('historial-contrasenas.*'),
                ])
            >
                <x-heroicon-o-key class="h-5 w-5 shrink-0" />
                <span class="sidebar-label">Historial de Contraseñas</span>
            </a>
        @endcan
    @endcanany

    <div class="mt-auto shrink-0 border-t border-border pt-4">
        <button
            type="button"
            x-data
            x-on:click="$dispatch('open-modal', 'mi-perfil')"
            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-ink-dim transition hover:bg-surface-2 hover:text-ink"
        >
            <x-heroicon-o-user-circle class="h-5 w-5 shrink-0" />
            <span class="sidebar-label">Mi perfil</span>
        </button>
    </div>
</nav>
