<?php

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\HorarioDia;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Academico\Services\HorarioService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $vista = 'calendario';

    // Instituto / curso_corto -- qué calendario se muestra, y qué tipo
    // precarga el formulario al crear un horario nuevo desde esa pestaña
    // (ver abrirModal()).
    public string $calendarioTipo = 'instituto';

    public bool $mostrarModal = false;

    public string $cicloFiltro = '';

    // Null mientras se crea uno nuevo; con valor, el modal edita ese horario.
    public ?int $editandoId = null;

    // 'instituto' o 'curso_corto' -- un curso corto no tiene Período de
    // matrícula ni Semestre (ver Horario::esDeCursoCorto()), así que
    // decide qué campos se muestran y de qué tipo sale el selector de
    // Curso. Fijo una vez elegido al crear; no se puede cambiar al editar.
    public string $tipo = 'instituto';

    public string $cursoId = '';

    public string $docenteId = '';

    public string $aulaId = '';

    public string $cicloId = '';

    // Solo filtra qué Grados/Cursos se ofrecen en los selects de abajo --
    // no se guarda en el Horario (la tabla solo tiene ciclo_id/grado_id).
    public string $programaEstudioId = '';

    public string $gradoId = '';

    // Las horas de clase son opcionales en ambos tipos -- este interruptor
    // decide si se piden o no. Por defecto viene activado para Instituto
    // (para no romper el comportamiento previo, donde eran obligatorias) y
    // desactivado para curso corto.
    public bool $conHoraDefinida = true;

    // Solo para curso corto: ni días ni horas -- directamente "sin
    // programación", el docente coordina las clases por su cuenta.
    public bool $horarioLibre = false;

    /**
     * Solo para el caso "sin hora definida" (ver $conHoraDefinida): al
     * crear, las franjas institucionales marcadas (pueden combinarse más
     * de una a la vez); al editar, los días sueltos ya reales del horario
     * (ver $diasSueltosSeleccionados) -- después de un arrastre en la
     * pestaña "Editar" los días pueden no calzar ya con ninguna franja
     * fija, así que editar no puede seguir atado a la abstracción de
     * franjas. Cuando SÍ hay hora definida, el día de cada bloque se
     * elige dentro del propio bloque (ver $bloques) y estas dos
     * propiedades no se usan.
     *
     * @var list<string>
     */
    public array $franjasSeleccionadas = [];

    /**
     * @var list<string>
     */
    public array $diasSueltosSeleccionados = [];

    /**
     * Un Horario puede tener varios bloques de horas el mismo día de
     * semana (ej. lunes mañana y lunes tarde) -- cada bloque elige su
     * propio día, así que ya no hay "un slot por día"; agregar dos
     * bloques con el mismo día cubre ese caso sin lógica especial (el
     * no-solape entre ellos lo valida HorarioService::validarDias()).
     * Para curso corto, horaInicioHora/horaFinHora guardan la hora en
     * formato 12h (01-12) y horaInicioAmPm/horaFinAmPm se usan; para
     * Instituto son 24h (00-23) y los AmPm quedan vacíos.
     *
     * @var list<array{dia: string, horaInicioHora: string, horaInicioMinuto: string, horaInicioAmPm: string, horaFinHora: string, horaFinMinuto: string, horaFinAmPm: string}>
     */
    public array $bloques = [];

    /**
     * Las 2 franjas institucionales, salvo que el curso elegido tenga
     * franjas propias marcadas en su ficha (Curso::franjas_permitidas) --
     * en ese caso solo se ofrecen esas.
     *
     * @return array<string, string>
     */
    public function franjasDisponibles(): array
    {
        $curso = $this->cursoId !== '' ? Curso::query()->find($this->cursoId) : null;
        $permitidas = $curso?->franjas_permitidas;

        return collect(FranjaHorarioEnum::cases())
            ->when(! empty($permitidas), fn (Collection $franjas) => $franjas->filter(
                fn (FranjaHorarioEnum $franja) => in_array($franja->value, $permitidas, true)
            ))
            ->mapWithKeys(fn (FranjaHorarioEnum $franja) => [$franja->value => $franja->label()])
            ->all();
    }

    /**
     * Si al elegir el curso alguna franja ya marcada deja de estar
     * permitida para él, se limpia -- no tendría sentido dejar
     * seleccionada una franja que ya no aparece en la lista.
     */
    public function updatedCursoId(): void
    {
        $disponibles = array_keys($this->franjasDisponibles());
        $this->franjasSeleccionadas = array_values(array_intersect($this->franjasSeleccionadas, $disponibles));
    }

    /**
     * Cambiar el Programa de Estudio vacía el Semestre y el Curso elegidos:
     * ambas listas dependen de este Programa (ver with()), así que una
     * elección anterior podría ya no estar entre las opciones.
     */
    public function updatedProgramaEstudioId(): void
    {
        $this->gradoId = '';
        $this->cursoId = '';
        $this->franjasSeleccionadas = [];
    }

    /**
     * Cambiar el Semestre vacía el Curso elegido: la lista de Cursos
     * depende del Semestre (solo se ofrecen los vinculados a ese Grado).
     */
    public function updatedGradoId(): void
    {
        $this->cursoId = '';
        $this->franjasSeleccionadas = [];
    }

    /**
     * @return array<string, string>
     */
    public function horasDisponibles(): array
    {
        return collect(range(0, 23))->mapWithKeys(fn (int $hora) => [sprintf('%02d', $hora) => sprintf('%02d', $hora)])->all();
    }

    /**
     * Horas en formato 12h (01-12), para el selector de curso corto.
     *
     * @return array<string, string>
     */
    public function horas12Disponibles(): array
    {
        return collect(range(1, 12))->mapWithKeys(fn (int $hora) => [sprintf('%02d', $hora) => sprintf('%02d', $hora)])->all();
    }

    /**
     * @return array<string, string>
     */
    public function amPmDisponibles(): array
    {
        return ['AM' => 'AM', 'PM' => 'PM'];
    }

    /**
     * Convierte hora 12h + AM/PM a hora 24h (string de 2 dígitos, "00"-"23")
     * para guardar -- el almacenamiento sigue siendo 24h sin importar el
     * selector que se use para capturarlo.
     */
    private function hora12A24(string $hora12, string $ampm): string
    {
        $hora = (int) $hora12 % 12;

        if ($ampm === 'PM') {
            $hora += 12;
        }

        return sprintf('%02d', $hora);
    }

    /**
     * Inverso de hora12A24(): para precargar el selector de curso corto al
     * editar un horario que ya tiene horas guardadas en 24h.
     *
     * @return array{0: string, 1: string}
     */
    private function hora24A12(string $hora24): array
    {
        $hora = (int) $hora24;
        $ampm = $hora >= 12 ? 'PM' : 'AM';
        $hora12 = $hora % 12;
        $hora12 = $hora12 === 0 ? 12 : $hora12;

        return [sprintf('%02d', $hora12), $ampm];
    }

    /**
     * @return array{dia: string, horaInicioHora: string, horaInicioMinuto: string, horaInicioAmPm: string, horaFinHora: string, horaFinMinuto: string, horaFinAmPm: string}
     */
    private function bloqueVacio(): array
    {
        return [
            'dia' => '',
            'horaInicioHora' => '',
            'horaInicioMinuto' => '',
            'horaInicioAmPm' => '',
            'horaFinHora' => '',
            'horaFinMinuto' => '',
            'horaFinAmPm' => '',
        ];
    }

    public function agregarBloque(): void
    {
        $this->bloques[] = $this->bloqueVacio();
    }

    public function quitarBloque(int $indice): void
    {
        unset($this->bloques[$indice]);
        $this->bloques = array_values($this->bloques);
    }

    /**
     * Los días que el bloque puede elegir: todos, salvo que el curso
     * elegido tenga franjas propias restringidas en su ficha
     * (Curso::franjas_permitidas), en cuyo caso solo los días de esas
     * franjas -- misma regla de negocio que antes aplicaba a
     * $franjasSeleccionadas, ahora expresada como lista de días.
     *
     * @return array<string, string>
     */
    private function diasDisponiblesParaBloques(): array
    {
        return collect(array_keys($this->franjasDisponibles()))
            ->flatMap(fn (string $valor) => FranjaHorarioEnum::from($valor)->dias())
            ->unique(fn (DiaSemanaEnum $dia) => $dia->value)
            ->mapWithKeys(fn (DiaSemanaEnum $dia) => [$dia->value => $dia->label()])
            ->all();
    }

    public function mount(): void
    {
        Gate::authorize('academico.ver');

        $activo = Ciclo::query()->where('estado', 'activo')->first();
        $this->cicloFiltro = $activo ? (string) $activo->id : '';
    }

    public function abrirModal(): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->reset([
            'editandoId', 'tipo', 'cursoId', 'docenteId', 'aulaId', 'programaEstudioId', 'gradoId', 'conHoraDefinida', 'horarioLibre',
            'franjasSeleccionadas', 'diasSueltosSeleccionados', 'bloques',
        ]);
        // Ya sabe qué tipo de horario crear según la pestaña de calendario
        // desde la que se abrió -- ver updatedTipo() para lo que cambia.
        $this->tipo = $this->calendarioTipo;
        $this->cicloId = $this->tipo === 'curso_corto' ? '' : $this->cicloFiltro;
        $this->conHoraDefinida = $this->tipo !== 'curso_corto';
        $this->bloques = $this->conHoraDefinida ? [$this->bloqueVacio()] : [];
        $this->mostrarModal = true;
    }

    /**
     * Al cambiar el tipo (solo posible al crear, no al editar) se limpia
     * el curso elegido: la lista de opciones cambia por completo (solo
     * académicos para Instituto, solo de capacitación para curso corto),
     * así que un curso ya elegido podría dejar de tener sentido.
     */
    public function updatedTipo(): void
    {
        $this->cursoId = '';
        $this->franjasSeleccionadas = [];
        $this->horarioLibre = false;

        if ($this->tipo === 'curso_corto') {
            $this->cicloId = '';
            $this->programaEstudioId = '';
            $this->gradoId = '';
            $this->conHoraDefinida = false;
        } else {
            $this->cicloId = $this->cicloFiltro;
            $this->conHoraDefinida = true;
        }

        $this->bloques = $this->conHoraDefinida ? [$this->bloqueVacio()] : [];
    }

    public function updatedHorarioLibre(): void
    {
        if ($this->horarioLibre) {
            $this->conHoraDefinida = false;
            $this->franjasSeleccionadas = [];
            $this->diasSueltosSeleccionados = [];
            $this->bloques = [];
        }
    }

    /**
     * Al activar "Definir hora de clase" arranca con un bloque vacío para
     * no mostrar la lista de bloques completamente en blanco sin ningún
     * botón visible más que "+ Agregar bloque".
     */
    public function updatedConHoraDefinida(): void
    {
        if ($this->conHoraDefinida && $this->bloques === []) {
            $this->bloques = [$this->bloqueVacio()];
        }
    }

    /**
     * Precarga el horario elegido (desde la tarjeta en la pestaña
     * "Editar") en el mismo modal que crea uno nuevo, pero con sus días
     * reales (no franjas) para poder representar cualquier combinación
     * que haya quedado tras un arrastre.
     */
    public function abrirModalEditar(int $horarioId): void
    {
        Gate::authorize('academico.gestionar');

        $horario = Horario::query()->with(['dias', 'grado'])->findOrFail($horarioId);

        $this->resetValidation();
        $this->editandoId = $horario->id;
        $this->tipo = $horario->esDeCursoCorto() ? 'curso_corto' : 'instituto';
        $this->cicloId = $horario->ciclo_id !== null ? (string) $horario->ciclo_id : '';
        $this->programaEstudioId = $horario->grado !== null ? (string) $horario->grado->programa_estudio_id : '';
        $this->gradoId = $horario->grado_id !== null ? (string) $horario->grado_id : '';
        $this->cursoId = (string) $horario->curso_id;
        $this->docenteId = (string) $horario->docente_id;
        $this->aulaId = (string) $horario->aula_id;
        $this->franjasSeleccionadas = [];
        $this->horarioLibre = $horario->esDeCursoCorto() && $horario->dias->isEmpty();
        $this->conHoraDefinida = $horario->dias->contains(fn (HorarioDia $dia) => $dia->hora_inicio !== null);

        if ($this->conHoraDefinida) {
            $this->diasSueltosSeleccionados = [];
            $this->bloques = $horario->dias
                ->filter(fn (HorarioDia $dia) => $dia->hora_inicio !== null)
                ->map(fn (HorarioDia $dia) => $this->bloqueDesdeHorarioDia($dia))
                ->values()
                ->all();
        } else {
            $this->diasSueltosSeleccionados = $horario->dias->pluck('dia_semana.value')->all();
            $this->bloques = [];
        }

        $this->mostrarModal = true;
    }

    /**
     * @return array{dia: string, horaInicioHora: string, horaInicioMinuto: string, horaInicioAmPm: string, horaFinHora: string, horaFinMinuto: string, horaFinAmPm: string}
     */
    private function bloqueDesdeHorarioDia(HorarioDia $dia): array
    {
        [$horaInicio, $minutoInicio] = explode(':', $dia->hora_inicio);
        [$horaFin, $minutoFin] = explode(':', $dia->hora_fin);

        if ($this->tipo === 'curso_corto') {
            [$horaInicio12, $amPmInicio] = $this->hora24A12($horaInicio);
            [$horaFin12, $amPmFin] = $this->hora24A12($horaFin);

            return [
                'dia' => $dia->dia_semana->value,
                'horaInicioHora' => $horaInicio12,
                'horaInicioMinuto' => $minutoInicio,
                'horaInicioAmPm' => $amPmInicio,
                'horaFinHora' => $horaFin12,
                'horaFinMinuto' => $minutoFin,
                'horaFinAmPm' => $amPmFin,
            ];
        }

        return [
            'dia' => $dia->dia_semana->value,
            'horaInicioHora' => $horaInicio,
            'horaInicioMinuto' => $minutoInicio,
            'horaInicioAmPm' => '',
            'horaFinHora' => $horaFin,
            'horaFinMinuto' => $minutoFin,
            'horaFinAmPm' => '',
        ];
    }

    /**
     * Los días efectivos del caso "sin hora definida": la unión de las
     * franjas marcadas al crear, o los días sueltos ya elegidos al
     * editar. Cuando SÍ hay hora definida, los días salen directamente de
     * cada bloque (ver $bloques) -- este método no aplica a ese caso.
     *
     * @return list<DiaSemanaEnum>
     */
    private function diasEfectivos(): array
    {
        if ($this->tipo === 'curso_corto' && $this->horarioLibre) {
            return [];
        }

        if ($this->editandoId !== null) {
            return array_map(fn (string $valor) => DiaSemanaEnum::from($valor), $this->diasSueltosSeleccionados);
        }

        return collect($this->franjasSeleccionadas)
            ->flatMap(fn (string $valor) => FranjaHorarioEnum::from($valor)->dias())
            ->unique(fn (DiaSemanaEnum $dia) => $dia->value)
            ->values()
            ->all();
    }

    public function guardar(HorarioService $service): void
    {
        Gate::authorize('academico.gestionar');

        $esCursoCorto = $this->tipo === 'curso_corto';
        $horarioLibre = $esCursoCorto && $this->horarioLibre;
        $pideHoras = ! $horarioLibre && $this->conHoraDefinida;

        $reglas = [
            'cursoId' => 'required|integer|exists:cursos,id',
            'docenteId' => 'required|integer|exists:users,id',
            'aulaId' => 'required|integer|exists:aulas,id',
        ];

        if ($esCursoCorto) {
            $reglas['cicloId'] = 'prohibited';
            $reglas['programaEstudioId'] = 'prohibited';
            $reglas['gradoId'] = 'prohibited';
        } else {
            $reglas['cicloId'] = 'required|integer|exists:ciclos,id';
            $reglas['programaEstudioId'] = 'required|integer|exists:programas_estudio,id';
            $reglas['gradoId'] = 'required|integer|exists:grados,id';
        }

        if ($pideHoras) {
            $diasPermitidos = array_keys($this->diasDisponiblesParaBloques());
            $reglas['bloques'] = 'required|array|min:1';

            foreach ($this->bloques as $indice => $bloque) {
                $reglas["bloques.{$indice}.dia"] = 'required|string|in:'.implode(',', $diasPermitidos);
                // numeric, no integer: "00" (el minuto tal como viene de la
                // BD, p.ej. de "18:00:00") no pasa la regla "integer" de
                // Laravel -- exige que (string)(int)$valor === $valor, y
                // "00" !== "0".
                $reglas["bloques.{$indice}.horaInicioMinuto"] = 'required|numeric|min:0|max:59';
                $reglas["bloques.{$indice}.horaFinMinuto"] = 'required|numeric|min:0|max:59';

                if ($esCursoCorto) {
                    $reglas["bloques.{$indice}.horaInicioHora"] = 'required|string|in:'.implode(',', array_keys($this->horas12Disponibles()));
                    $reglas["bloques.{$indice}.horaInicioAmPm"] = 'required|string|in:'.implode(',', array_keys($this->amPmDisponibles()));
                    $reglas["bloques.{$indice}.horaFinHora"] = 'required|string|in:'.implode(',', array_keys($this->horas12Disponibles()));
                    $reglas["bloques.{$indice}.horaFinAmPm"] = 'required|string|in:'.implode(',', array_keys($this->amPmDisponibles()));
                } else {
                    $reglas["bloques.{$indice}.horaInicioHora"] = 'required|string|in:'.implode(',', array_keys($this->horasDisponibles()));
                    $reglas["bloques.{$indice}.horaFinHora"] = 'required|string|in:'.implode(',', array_keys($this->horasDisponibles()));
                }
            }
        } elseif (! $horarioLibre) {
            if ($this->editandoId === null) {
                $reglas['franjasSeleccionadas'] = 'required|array|min:1';
                $reglas['franjasSeleccionadas.*'] = 'string|in:'.implode(',', array_keys($this->franjasDisponibles()));
            } else {
                $reglas['diasSueltosSeleccionados'] = 'required|array|min:1';
                $reglas['diasSueltosSeleccionados.*'] = 'string|in:'.implode(',', array_map(fn (DiaSemanaEnum $dia) => $dia->value, DiaSemanaEnum::cases()));
            }
        }

        $this->validate($reglas);

        // El curso y el semestre elegidos deben estar realmente vinculados
        // (Curso::grados()) -- un curso corto no tiene semestre, así que
        // no aplica este chequeo.
        if (! $esCursoCorto) {
            $cursoPerteneceAlGrado = Curso::query()
                ->whereKey((int) $this->cursoId)
                ->whereHas('grados', fn ($query) => $query->whereKey((int) $this->gradoId))
                ->exists();

            if (! $cursoPerteneceAlGrado) {
                $this->addError('cursoId', 'El curso elegido no está vinculado al semestre elegido.');

                return;
            }
        }

        if ($pideHoras) {
            $diasParaGuardar = array_map(function (array $bloque) use ($esCursoCorto) {
                $minutoInicio = sprintf('%02d', (int) $bloque['horaInicioMinuto']);
                $minutoFin = sprintf('%02d', (int) $bloque['horaFinMinuto']);

                if ($esCursoCorto) {
                    $horaInicio24 = $this->hora12A24($bloque['horaInicioHora'], $bloque['horaInicioAmPm']);
                    $horaFin24 = $this->hora12A24($bloque['horaFinHora'], $bloque['horaFinAmPm']);

                    return [
                        'dia_semana' => DiaSemanaEnum::from($bloque['dia']),
                        'hora_inicio' => "{$horaInicio24}:{$minutoInicio}:00",
                        'hora_fin' => "{$horaFin24}:{$minutoFin}:00",
                    ];
                }

                return [
                    'dia_semana' => DiaSemanaEnum::from($bloque['dia']),
                    'hora_inicio' => "{$bloque['horaInicioHora']}:{$minutoInicio}:00",
                    'hora_fin' => "{$bloque['horaFinHora']}:{$minutoFin}:00",
                ];
            }, $this->bloques);
        } else {
            $diasParaGuardar = array_map(
                fn (DiaSemanaEnum $dia) => ['dia_semana' => $dia, 'hora_inicio' => null, 'hora_fin' => null],
                $this->diasEfectivos(),
            );
        }

        $datos = [
            'curso_id' => (int) $this->cursoId,
            'docente_id' => (int) $this->docenteId,
            'aula_id' => (int) $this->aulaId,
            'ciclo_id' => $esCursoCorto ? null : (int) $this->cicloId,
            'grado_id' => $esCursoCorto ? null : (int) $this->gradoId,
            'dias' => $diasParaGuardar,
        ];

        if ($this->editandoId === null) {
            $service->crear($datos);
            session()->flash('status', 'Horario creado correctamente.');
        } else {
            $service->actualizar(Horario::query()->findOrFail($this->editandoId), $datos);
            session()->flash('status', 'Horario actualizado correctamente.');
        }

        $this->mostrarModal = false;
    }

    /**
     * Arrastrar-y-soltar en la pestaña "Editar": mueve un solo día
     * (HorarioDia) al día donde se soltó, conservando su hora. Si choca
     * con otro horario en la misma aula/docente, HorarioService::moverDia()
     * lanza la misma ValidationException que ya usa crear()/actualizar();
     * se muestra como error en vez de mover la tarjeta.
     */
    public function moverDia(int $horarioDiaId, string $nuevoDia, HorarioService $service): void
    {
        Gate::authorize('academico.gestionar');

        $dia = HorarioDia::query()->findOrFail($horarioDiaId);
        $nuevoDiaEnum = DiaSemanaEnum::from($nuevoDia);

        if ($dia->dia_semana === $nuevoDiaEnum) {
            return;
        }

        try {
            $service->moverDia($dia, $nuevoDiaEnum);
            session()->flash('status', 'Horario movido correctamente.');
        } catch (ValidationException $e) {
            $this->addError('dias', $e->validator->errors()->first());
        }
    }

    public function with(HorarioService $service): array
    {
        // Los horarios de curso corto no tienen ciclo (son indefinidos en
        // el tiempo), así que se muestran siempre, sin importar el filtro
        // de Período de matrícula elegido para los de Instituto.
        $horarios = ($this->cicloFiltro ? $service->delCiclo((int) $this->cicloFiltro) : collect())
            ->merge($service->deCursoCorto());

        $cicloActivoParaCalendario = $this->cicloFiltro !== '' ? Ciclo::query()->find($this->cicloFiltro) : null;

        // Semestre (Grado) y Curso se eligen en cascada: Programa de Estudio
        // -> Semestre -> Curso. Sin el paso anterior elegido, la lista
        // siguiente queda vacía en vez de mostrar todo mezclado (antes
        // "Semestre" listaba los Grados de todos los Programas juntos, con
        // nombres repetidos como "I SEMESTRE" de carreras distintas).
        $cursos = match (true) {
            $this->tipo === 'curso_corto' => Curso::query()->where('activo', true)->where('tipo', 'capacitacion')->orderBy('nombre')->get(),
            $this->gradoId !== '' => Curso::query()->where('activo', true)->where('tipo', 'academico')
                ->whereHas('grados', fn ($query) => $query->whereKey((int) $this->gradoId))
                ->orderBy('nombre')->get(),
            default => new Collection,
        };

        return [
            'ciclos' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'eventosInstituto' => $this->eventosCalendario($horarios->filter(fn (Horario $h) => ! $h->esDeCursoCorto()), $cicloActivoParaCalendario),
            'eventosCursoCorto' => $this->eventosCalendario($horarios->filter(fn (Horario $h) => $h->esDeCursoCorto()), null),
            'horariosPorFranja' => $this->agruparPorFranjaYGrado($horarios),
            'horarioDiasPorDia' => $this->agruparDiasParaLaGrilla($horarios),
            'cursos' => $cursos,
            'docentes' => User::role('docente')->orderBy('name')->get(),
            'aulas' => Aula::query()->where('activa', true)->orderBy('nombre')->get(),
            'programasEstudio' => ProgramaEstudio::query()->where('activo', true)->orderBy('nombre')->get(),
            'grados' => $this->programaEstudioId !== ''
                ? Grado::query()->where('activo', true)->where('programa_estudio_id', (int) $this->programaEstudioId)->orderBy('orden')->get()
                : new Collection,
            'cursoTieneFranjasRestringidas' => $this->cursoId !== '' && ! empty(Curso::query()->find($this->cursoId)?->franjas_permitidas),
            'franjas' => $this->franjasDisponibles(),
            'horas' => $this->horasDisponibles(),
            'horas12' => $this->horas12Disponibles(),
            'amPm' => $this->amPmDisponibles(),
            'diasSemana' => DiaSemanaEnum::ordenSemana(),
            'diasDisponiblesParaBloques' => $this->diasDisponiblesParaBloques(),
        ];
    }

    /**
     * Los HorarioDia de estos horarios, en el formato de evento recurrente
     * de FullCalendar (ver x-data="horariosCalendario(...)" en app.js):
     * daysOfWeek + startTime/endTime para los que tienen hora definida, o
     * allDay para los que no (ver $conHoraDefinida) -- FullCalendar los
     * muestra en la franja "Sin hora fija" en vez de en la grilla de horas.
     * Un horario de Instituto se acota a las fechas de su propio Ciclo
     * (startRecur/endRecur); uno de curso corto, sin ciclo, no se acota.
     *
     * @param  Collection<int, Horario>  $horarios
     * @return list<array<string, mixed>>
     */
    private function eventosCalendario(Collection $horarios, ?Ciclo $ciclo): array
    {
        return $horarios
            ->flatMap(function (Horario $horario) use ($ciclo) {
                return $horario->dias->map(function (HorarioDia $dia) use ($horario, $ciclo) {
                    $evento = [
                        'title' => "{$horario->curso->nombre} — {$horario->docente->name}",
                        'daysOfWeek' => [$dia->dia_semana->numeroCarbon()],
                        'extendedProps' => ['horarioId' => $horario->id],
                        'color' => $horario->esDeCursoCorto() ? '#18BEBC' : '#165D96',
                    ];

                    if ($dia->hora_inicio !== null && $dia->hora_fin !== null) {
                        $evento['startTime'] = $dia->hora_inicio;
                        $evento['endTime'] = $dia->hora_fin;
                    } else {
                        $evento['allDay'] = true;
                    }

                    if ($ciclo !== null) {
                        $evento['startRecur'] = $ciclo->fecha_inicio->format('Y-m-d');
                        $evento['endRecur'] = $ciclo->fecha_fin->format('Y-m-d');
                    }

                    return $evento;
                });
            })
            ->values()
            ->all();
    }

    /**
     * La vista organizada como la institución realmente trabaja: primero
     * por franja (Lunes a Viernes / Sábados y Domingos), y dentro de
     * cada una por grado -- así se ven de una los grupos A y B de un mismo
     * grado, en vez de una tabla plana ordenada por curso.
     *
     * @param  Collection<int, Horario>  $horarios
     * @return Collection<int, array{label: string, porGrado: Collection<string, Collection<int, Horario>>}>
     */
    private function agruparPorFranjaYGrado($horarios)
    {
        $grupos = collect(FranjaHorarioEnum::cases())
            ->mapWithKeys(function (FranjaHorarioEnum $franja) use ($horarios) {
                $deLaFranja = $horarios->filter(fn (Horario $horario) => $horario->franja() === $franja);

                return [$franja->value => [
                    'label' => $franja->label(),
                    'porGrado' => $deLaFranja->groupBy(fn (Horario $horario) => $this->nombreDeAgrupacion($horario))->sortKeys(),
                ]];
            });

        $sinFranja = $horarios->filter(fn (Horario $horario) => $horario->franja() === null);

        if ($sinFranja->isNotEmpty()) {
            $grupos['otros'] = [
                'label' => 'Otros días',
                'porGrado' => $sinFranja->groupBy(fn (Horario $horario) => $this->nombreDeAgrupacion($horario))->sortKeys(),
            ];
        }

        return $grupos->filter(fn (array $grupo) => $grupo['porGrado']->isNotEmpty());
    }

    /**
     * Un horario de curso corto no tiene grado -- se agrupa por el
     * nombre del curso en su lugar, con una etiqueta que deja claro que
     * es un curso corto y no un semestre real.
     */
    private function nombreDeAgrupacion(Horario $horario): string
    {
        return $horario->esDeCursoCorto() ? "Curso corto: {$horario->curso->nombre}" : $horario->grado->nombre;
    }

    /**
     * Los HorarioDia de estos horarios, agrupados por día de la semana --
     * la fuente de datos de la grilla semanal de la pestaña "Editar".
     *
     * @param  Collection<int, Horario>  $horarios
     * @return Collection<string, Collection<int, HorarioDia>>
     */
    private function agruparDiasParaLaGrilla($horarios)
    {
        return $horarios
            ->flatMap(fn (Horario $horario) => $horario->dias)
            ->groupBy(fn (HorarioDia $dia) => $dia->dia_semana->value);
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Horarios</h1>
        <p class="mt-1 text-sm text-ink-dim">Un aula y un docente no pueden tener dos clases a la misma hora.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <x-primary-button type="button" wire:click="abrirModal" class="gap-2">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo horario
            </x-primary-button>
        </div>
    @endcan

    @if (session('status'))
        <x-alert class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-select-input
            wire:model.live="cicloFiltro"
            placeholder="Selecciona un ciclo…"
            class="w-full sm:max-w-xs"
            :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
        />

        <div class="flex gap-1 rounded-md border border-border bg-surface p-1">
            <button
                type="button"
                wire:click="$set('vista', 'calendario')"
                @class(['rounded px-3 py-1.5 text-sm font-medium', 'bg-accent text-white' => $vista === 'calendario', 'text-ink-dim' => $vista !== 'calendario'])
            >Calendario</button>
            <button
                type="button"
                wire:click="$set('vista', 'lista')"
                @class(['rounded px-3 py-1.5 text-sm font-medium', 'bg-accent text-white' => $vista === 'lista', 'text-ink-dim' => $vista !== 'lista'])
            >Lista</button>
            @can('academico.gestionar')
                <button
                    type="button"
                    wire:click="$set('vista', 'editar')"
                    @class(['rounded px-3 py-1.5 text-sm font-medium', 'bg-accent text-white' => $vista === 'editar', 'text-ink-dim' => $vista !== 'editar'])
                >Editar</button>
            @endcan
        </div>
    </div>

    @if ($vista === 'calendario')
        <div class="mb-4 flex gap-1 rounded-md border border-border bg-surface p-1 w-fit">
            <button
                type="button"
                wire:click="$set('calendarioTipo', 'instituto')"
                @class(['rounded px-3 py-1.5 text-sm font-medium', 'bg-accent text-white' => $calendarioTipo === 'instituto', 'text-ink-dim' => $calendarioTipo !== 'instituto'])
            >Instituto</button>
            <button
                type="button"
                wire:click="$set('calendarioTipo', 'curso_corto')"
                @class(['rounded px-3 py-1.5 text-sm font-medium', 'bg-accent text-white' => $calendarioTipo === 'curso_corto', 'text-ink-dim' => $calendarioTipo !== 'curso_corto'])
            >Curso corto</button>
        </div>

        @php
            $eventosActivos = $calendarioTipo === 'instituto' ? $eventosInstituto : $eventosCursoCorto;
        @endphp
        {{-- Sin wire:ignore a propósito: el wire:key cambia con los datos
             (no solo con el tipo/ciclo) para que Livewire reconstruya el
             nodo -- y con él, reinicialice Alpine/FullCalendar con los
             eventos al día -- cada vez que un horario se crea, edita o
             mueve (mismo motivo que x-select-input más abajo). --}}
        <div wire:key="calendario-{{ $calendarioTipo }}-{{ $cicloFiltro }}-{{ md5(json_encode($eventosActivos)) }}" class="rounded-2xl border border-border bg-surface p-3 shadow-sm">
            <div x-data="horariosCalendario(@js($eventosActivos))"></div>
        </div>
    @endif

    @if ($vista === 'lista')
        @forelse ($horariosPorFranja as $grupoFranja)
            <div class="mb-6">
                <h2 class="mb-2 font-display text-lg text-ink">{{ $grupoFranja['label'] }}</h2>

                @foreach ($grupoFranja['porGrado'] as $nombreGrado => $horariosDelGrado)
                    <div class="mb-4 overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                        <div class="border-b border-border bg-surface-2 px-4 py-2 font-display text-sm text-ink">{{ $nombreGrado }}</div>
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead class="bg-surface-2">
                                <tr>
                                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Curso</th>
                                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Docente</th>
                                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Aula</th>
                                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Horario</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($horariosDelGrado->sortBy(fn ($horario) => $horario->curso->nombre) as $horario)
                                    <tr wire:key="horario-{{ $horario->id }}">
                                        <td class="px-4 py-3 font-medium text-ink">{{ $horario->curso->nombre }}</td>
                                        <td class="px-4 py-3 text-ink-dim">{{ $horario->docente->name }}</td>
                                        <td class="px-4 py-3 text-ink-dim">{{ $horario->aula->nombre }}</td>
                                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $horario->diasResumen() }}</td>
                                        <td class="px-4 py-3 text-right">
                                            @can('academico.gestionar')
                                                <button type="button" wire:click="abrirModalEditar({{ $horario->id }})" class="text-sm font-medium text-accent hover:underline">
                                                    Editar
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface shadow-sm px-4 py-8 text-center text-sm text-ink-faint">
                {{ $cicloFiltro ? 'Este ciclo no tiene horarios todavía.' : 'Selecciona un ciclo para ver sus horarios.' }}
            </div>
        @endforelse
    @else
        <p class="mb-3 text-xs text-ink-faint">Arrastra una tarjeta a otro día para reprogramarla, o haz clic en ella para editar curso, docente, aula u horas.</p>
        <x-input-error :messages="$errors->get('dias')" class="mb-3" />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7" x-data>
            @foreach ($diasSemana as $dia)
                <div
                    wire:key="columna-{{ $dia->value }}"
                    class="min-h-[160px] rounded-2xl border border-border bg-surface p-2"
                    x-on:dragover.prevent
                    x-on:drop.prevent="$wire.moverDia($event.dataTransfer.getData('text/plain'), '{{ $dia->value }}')"
                >
                    <p class="mb-2 text-center font-mono text-xs uppercase tracking-wide text-ink-faint">{{ $dia->label() }}</p>

                    @foreach (($horarioDiasPorDia[$dia->value] ?? []) as $horarioDia)
                        <div
                            wire:key="tarjeta-{{ $horarioDia->id }}"
                            draggable="true"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $horarioDia->id }}')"
                            wire:click="abrirModalEditar({{ $horarioDia->horario_id }})"
                            class="mb-2 cursor-move rounded-lg border border-border bg-surface-2 p-2 text-xs transition hover:border-accent"
                        >
                            <p class="font-medium text-ink">{{ $horarioDia->horario->curso->nombre }}</p>
                            <p class="text-ink-dim">{{ $horarioDia->horario->docente->name }}</p>
                            <p class="text-ink-faint">
                                {{ $horarioDia->horario->esDeCursoCorto() ? 'Curso corto' : $horarioDia->horario->grado->nombre }}
                                @if ($horarioDia->hora_inicio !== null && $horarioDia->hora_fin !== null)
                                    · {{ substr($horarioDia->hora_inicio, 0, 5) }}–{{ substr($horarioDia->hora_fin, 0, 5) }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
                <h2 class="font-display text-lg text-ink">{{ $editandoId === null ? 'Nuevo horario' : 'Editar horario' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    @if ($editandoId === null)
                        <div>
                            <x-input-label for="tipo" value="Tipo de horario" />
                            <x-select-input
                                wire:model.live="tipo"
                                id="tipo"
                                class="mt-1 block w-full"
                                :options="['instituto' => 'Instituto', 'curso_corto' => 'Curso corto']"
                            />
                            <p class="mt-1 text-xs text-ink-faint">Curso corto: sin período de matrícula ni semestre, y las horas de clase son opcionales.</p>
                        </div>
                    @else
                        <div>
                            <x-input-label value="Tipo de horario" />
                            <p class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-ink-dim">{{ $tipo === 'curso_corto' ? 'Curso corto' : 'Instituto' }}</p>
                            <p class="mt-1 text-xs text-ink-faint">El tipo no se puede cambiar una vez creado el horario.</p>
                        </div>
                    @endif

                    @if ($tipo === 'instituto')
                        <div wire:key="campo-periodo">
                            <x-input-label for="cicloId" value="Período de matrícula" />
                            <x-select-input
                                wire:model="cicloId"
                                id="cicloId"
                                class="mt-1 block w-full"
                                :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                            />
                            <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                        </div>

                        {{-- Programa de Estudio -> Semestre -> Curso es una
                             cascada: cada select solo muestra opciones del
                             anterior (ver with()), para no mezclar Semestres
                             de carreras distintas con el mismo nombre (ej.
                             dos "I SEMESTRE"). wire:key con el valor del
                             select anterior obliga a Livewire a reconstruir
                             el siguiente -- x-select-input arma su lista de
                             opciones una sola vez dentro de x-data (Alpine),
                             así que un simple cambio de atributos no basta
                             para que recoja la lista filtrada nueva. --}}
                        <div class="grid grid-cols-2 gap-4" wire:key="campos-programa-semestre">
                            <div>
                                <x-input-label for="programaEstudioId" value="Programa de estudio" />
                                <x-select-input
                                    wire:model.live="programaEstudioId"
                                    id="programaEstudioId"
                                    class="mt-1 block w-full"
                                    placeholder="Selecciona…"
                                    :options="collect($programasEstudio)->mapWithKeys(fn ($programa) => [$programa->id => $programa->nombre])"
                                />
                                <x-input-error :messages="$errors->get('programaEstudioId')" class="mt-1" />
                            </div>
                            <div wire:key="campo-semestre-{{ $programaEstudioId }}">
                                <x-input-label for="gradoId" value="Semestre" />
                                <x-select-input
                                    wire:model.live="gradoId"
                                    id="gradoId"
                                    class="mt-1 block w-full"
                                    :disabled="$programaEstudioId === ''"
                                    :placeholder="$programaEstudioId === '' ? 'Elige un programa primero' : 'Selecciona…'"
                                    :options="collect($grados)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])"
                                />
                                <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                            </div>
                        </div>
                    @endif

                    <div wire:key="campo-curso-{{ $tipo }}-{{ $gradoId }}">
                        <x-input-label for="cursoId" value="Curso" />
                        <x-select-input
                            wire:model.live="cursoId"
                            id="cursoId"
                            class="mt-1 block w-full"
                            :disabled="$tipo === 'instituto' && $gradoId === ''"
                            :placeholder="$tipo === 'instituto' && $gradoId === '' ? 'Elige un semestre primero' : 'Selecciona…'"
                            :options="collect($cursos)->mapWithKeys(fn ($curso) => [$curso->id => $curso->nombre.' ('.$curso->codigo.')'])"
                        />
                        <x-input-error :messages="$errors->get('cursoId')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="docenteId" value="Docente" />
                            <x-select-input
                                wire:model="docenteId"
                                id="docenteId"
                                class="mt-1 block w-full"
                                :options="collect($docentes)->mapWithKeys(fn ($docente) => [$docente->id => $docente->name])"
                            />
                            <x-input-error :messages="$errors->get('docenteId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="aulaId" value="Aula" />
                            <x-select-input
                                wire:model="aulaId"
                                id="aulaId"
                                class="mt-1 block w-full"
                                :options="collect($aulas)->mapWithKeys(fn ($aula) => [$aula->id => $aula->nombre])"
                            />
                            <x-input-error :messages="$errors->get('aulaId')" class="mt-1" />
                        </div>
                    </div>

                    @if ($tipo === 'curso_corto')
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" wire:model.live="horarioLibre" class="rounded border-border text-accent focus:ring-accent">
                            Horario libre
                        </label>
                        <p class="-mt-2 text-xs text-ink-faint">Sin días ni horas fijas: el docente coordina las clases por su cuenta.</p>
                    @endif

                    @unless ($tipo === 'curso_corto' && $horarioLibre)
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" wire:model.live="conHoraDefinida" class="rounded border-border text-accent focus:ring-accent">
                            Definir hora de clase
                        </label>
                        <p class="-mt-2 text-xs text-ink-faint">Opcional: el horario puede quedar solo con sus días marcados, sin hora fija -- se puede completar después (mientras tanto, Asistencia queda bloqueada con un aviso).</p>

                        @if ($conHoraDefinida)
                            {{-- Bloques de horario: cada uno elige su propio día, así
                                 que agregar dos bloques con el mismo día (ej. lunes
                                 mañana y lunes tarde) ya cubre más de una clase el
                                 mismo día -- el no-solape entre ellos lo valida
                                 HorarioService::validarDias(). --}}
                            <div>
                                <x-input-label value="Bloques de horario" />
                                @if ($cursoTieneFranjasRestringidas)
                                    <p class="mt-1 text-xs text-ink-faint">Solo se permiten los días de la franja habilitada para este curso.</p>
                                @endif

                                <div class="mt-2 space-y-3">
                                    @foreach ($bloques as $indice => $bloque)
                                        <div class="rounded-md border border-border p-3" wire:key="bloque-{{ $indice }}">
                                            <div class="flex items-end justify-between gap-2">
                                                <div class="flex-1">
                                                    <x-input-label value="Día" />
                                                    <x-select-input
                                                        wire:model="bloques.{{ $indice }}.dia"
                                                        class="mt-1 block w-full"
                                                        placeholder="Selecciona…"
                                                        :options="$diasDisponiblesParaBloques"
                                                    />
                                                    <x-input-error :messages="$errors->get('bloques.'.$indice.'.dia')" class="mt-1" />
                                                </div>
                                                @if (count($bloques) > 1)
                                                    <button type="button" wire:click="quitarBloque({{ $indice }})" class="shrink-0 pb-2 text-xs font-medium text-danger hover:underline">
                                                        Quitar
                                                    </button>
                                                @endif
                                            </div>

                                            <div class="mt-3 grid grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label value="Hora inicio" />
                                                    @if ($tipo === 'curso_corto')
                                                        <div class="mt-1 grid grid-cols-3 gap-2">
                                                            <x-select-input wire:model="bloques.{{ $indice }}.horaInicioHora" placeholder="Hora" :options="$horas12" />
                                                            <input type="number" min="0" max="59" wire:model="bloques.{{ $indice }}.horaInicioMinuto" placeholder="Min" class="rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                                            <x-select-input wire:model="bloques.{{ $indice }}.horaInicioAmPm" placeholder="AM/PM" :options="$amPm" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get('bloques.'.$indice.'.horaInicioAmPm')" class="mt-1" />
                                                    @else
                                                        <div class="mt-1 grid grid-cols-2 gap-2">
                                                            <x-select-input wire:model="bloques.{{ $indice }}.horaInicioHora" placeholder="Hora" :options="$horas" />
                                                            <input type="number" min="0" max="59" wire:model="bloques.{{ $indice }}.horaInicioMinuto" placeholder="Min" class="rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                                        </div>
                                                    @endif
                                                    <x-input-error :messages="$errors->get('bloques.'.$indice.'.horaInicioHora')" class="mt-1" />
                                                    <x-input-error :messages="$errors->get('bloques.'.$indice.'.horaInicioMinuto')" class="mt-1" />
                                                </div>
                                                <div>
                                                    <x-input-label value="Hora fin" />
                                                    @if ($tipo === 'curso_corto')
                                                        <div class="mt-1 grid grid-cols-3 gap-2">
                                                            <x-select-input wire:model="bloques.{{ $indice }}.horaFinHora" placeholder="Hora" :options="$horas12" />
                                                            <input type="number" min="0" max="59" wire:model="bloques.{{ $indice }}.horaFinMinuto" placeholder="Min" class="rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                                            <x-select-input wire:model="bloques.{{ $indice }}.horaFinAmPm" placeholder="AM/PM" :options="$amPm" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get('bloques.'.$indice.'.horaFinAmPm')" class="mt-1" />
                                                    @else
                                                        <div class="mt-1 grid grid-cols-2 gap-2">
                                                            <x-select-input wire:model="bloques.{{ $indice }}.horaFinHora" placeholder="Hora" :options="$horas" />
                                                            <input type="number" min="0" max="59" wire:model="bloques.{{ $indice }}.horaFinMinuto" placeholder="Min" class="rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                                        </div>
                                                    @endif
                                                    <x-input-error :messages="$errors->get('bloques.'.$indice.'.horaFinHora')" class="mt-1" />
                                                    <x-input-error :messages="$errors->get('bloques.'.$indice.'.horaFinMinuto')" class="mt-1" />
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <x-secondary-button type="button" wire:click="agregarBloque" class="mt-3">+ Agregar bloque</x-secondary-button>
                                <x-input-error :messages="$errors->get('bloques')" class="mt-1" />
                            </div>
                        @elseif ($editandoId === null)
                            <div>
                                <x-input-label value="Días de clase" />
                                <p class="mt-1 text-xs text-ink-faint">Ningún curso se dicta en un día suelto: elige una o varias franjas.</p>
                                @if ($cursoTieneFranjasRestringidas)
                                    <p class="mt-1 text-xs text-ink-faint">Solo se muestran las franjas permitidas para este curso.</p>
                                @endif

                                <div class="mt-2 space-y-2">
                                    @foreach ($franjas as $valor => $etiqueta)
                                        <label class="flex items-center gap-2 rounded-md border border-border p-3 text-sm text-ink">
                                            <input
                                                type="checkbox"
                                                value="{{ $valor }}"
                                                wire:model.live="franjasSeleccionadas"
                                                class="rounded border-border text-accent focus:ring-accent"
                                            >
                                            {{ $etiqueta }}
                                        </label>
                                    @endforeach
                                </div>

                                <x-input-error :messages="$errors->get('franjasSeleccionadas')" class="mt-1" />
                            </div>
                        @else
                            <div>
                                <x-input-label value="Días de clase" />
                                <p class="mt-1 text-xs text-ink-faint">Los días reales de este horario -- puedes agregar o quitar días sueltos aquí.</p>

                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @foreach ($diasSemana as $dia)
                                        <label class="flex items-center gap-2 rounded-md border border-border p-2 text-sm text-ink">
                                            <input
                                                type="checkbox"
                                                value="{{ $dia->value }}"
                                                wire:model.live="diasSueltosSeleccionados"
                                                class="rounded border-border text-accent focus:ring-accent"
                                            >
                                            {{ $dia->label() }}
                                        </label>
                                    @endforeach
                                </div>

                                <x-input-error :messages="$errors->get('diasSueltosSeleccionados')" class="mt-1" />
                            </div>
                        @endif
                    @endunless

                    {{-- Los choques de aula/docente los valida HorarioService::crear()/actualizar(), que lanza sus errores bajo la clave "dias". --}}
                    <x-input-error :messages="$errors->get('dias')" class="mt-1" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">{{ $editandoId === null ? 'Crear horario' : 'Guardar cambios' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
