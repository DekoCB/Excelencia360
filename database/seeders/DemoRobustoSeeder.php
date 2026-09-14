<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Enums\TipoPublicacionEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\ForoService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\PublicacionService;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Incidencias\Enums\TipoIncidenciaEnum;
use App\Modules\Incidencias\Services\IncidenciaService;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Notificaciones\Services\CampaniaWhatsappService;
use App\Modules\Notificaciones\Services\PlantillaService;
use App\Modules\Notificaciones\Services\RecordatorioCuotaService;
use App\Modules\Pagos\Enums\MedioCuentaEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Enums\TipoBilleteraEnum;
use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Modules\Pagos\Services\ConceptoPagoService;
use App\Modules\Pagos\Services\CuentaBancariaService;
use App\Modules\Pagos\Services\PagoService;
use App\Modules\Pagos\Services\PlanPagoService;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Amplía los datos de demostración sembrados por los seeders de cada
 * módulo para que el sistema se vea poblado de punta a punta: más
 * docentes, cursos, horarios, estudiantes matriculados con roster real
 * en Aula Virtual, Asistencia y Evaluaciones, y un par de libretas ya
 * generadas. No reemplaza a los seeders existentes, los complementa.
 */
class DemoRobustoSeeder extends Seeder
{
    /** @var list<string> */
    private array $nombresMasculinos = [
        'Carlos', 'Luis', 'José', 'Miguel', 'Jorge', 'Manuel', 'Pedro', 'Juan',
        'Ricardo', 'Fernando', 'Alberto', 'Raúl', 'Víctor', 'Edwin', 'Marco',
        'Julio', 'Hernán', 'Percy', 'Wilson', 'Elmer',
    ];

    /** @var list<string> */
    private array $nombresFemeninos = [
        'María', 'Rosa', 'Carmen', 'Ana', 'Luz', 'Silvia', 'Yolanda', 'Karina',
        'Milagros', 'Gladys', 'Verónica', 'Pilar', 'Fiorella', 'Yesenia',
        'Elizabeth', 'Norma', 'Doris', 'Betty', 'Susana', 'Katherine',
    ];

    /** @var list<string> */
    private array $apellidos = [
        'Quispe', 'Mamani', 'Huamán', 'Torres', 'Flores', 'Rojas', 'Vargas',
        'Condori', 'Ccama', 'Chuquimango', 'Ramírez', 'Salazar', 'Gonzales',
        'Fernández', 'Cruz', 'Reyes', 'Paredes', 'Aquino', 'Bautista', 'Medina',
        'Cárdenas', 'Vilca', 'Ochoa', 'Zúñiga', 'Palomino',
    ];

    private int $secuenciaDniDocente = 61000001;

    private int $secuenciaDniEstudiante = 62000001;

    private int $secuenciaDniApoderado = 63000001;

    private int $secuenciaCelular = 1;

    public function run(): void
    {
        $ciclo = Ciclo::query()->where('estado', 'activo')->first();

        if (! $ciclo) {
            return;
        }

        $grados = Grado::query()->orderBy('orden')->get();

        $this->asegurarCursos($grados);
        $aulas = $this->asegurarAulas();
        $docentes = $this->crearDocentes(5);
        $this->crearHorarios($ciclo, $aulas, $docentes);
        $this->crearEstudiantesYMatriculas($ciclo, $grados);
        $this->poblarSiagieAnual($grados);
        $this->diversificarEstadosDeEstudiantes();

        $horarios = Horario::query()->where('ciclo_id', $ciclo->id)->get();

        $this->poblarAulaVirtual($horarios);
        $this->registrarAsistencia($horarios);
        $this->registrarEvaluacionesYLibretas($ciclo, $horarios);
        $this->poblarPagos();
        $this->poblarCertificados();
        $this->poblarIncidencias();
        $this->poblarNotificaciones();
    }

    /**
     * @param  Collection<int, Grado>  $grados
     */
    /**
     * Las 8 asignaturas oficiales de la libreta de notas de CEBA (mismo
     * orden en que aparecen impresas) -- ver LibretaService/pdf.libreta.
     * firstOrCreate() por grado_id+nombre en vez de por posición: así, si
     * un grado ya tiene algún curso del catálogo anterior, no se duplica,
     * y sigue siendo seguro volver a correr el seeder.
     */
    private function asegurarCursos(Collection $grados): void
    {
        $catalogo = [
            'Matemática',
            'Ciencia Tecnología y Salud',
            'Comunicación',
            'Desarrollo personal y ciudadano',
            'Inglés',
            'Religión',
            'Educación para el trabajo',
            'Educación física',
        ];
        $horasPorCurso = 80;

        foreach ($grados as $grado) {
            foreach ($catalogo as $indice => $nombre) {
                Curso::query()->firstOrCreate(
                    ['grado_id' => $grado->id, 'nombre' => $nombre],
                    [
                        'codigo' => "G{$grado->id}-".str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT),
                        'horas' => $horasPorCurso,
                    ],
                );
            }
        }
    }

    /**
     * Aulas "sueltas" adicionales (sin ciclo_id/letra) para que
     * crearHorarios() tenga suficiente espacio disponible entre todos los
     * cursos de los 4 grados sin toparse con el límite de aulas libres.
     *
     * @return Collection<int, Aula>
     */
    private function asegurarAulas(): Collection
    {
        foreach ([
            ['nombre' => 'Aula 3', 'capacidad' => 28, 'ubicacion' => 'Piso 2'],
            ['nombre' => 'Aula 4', 'capacidad' => 20, 'ubicacion' => 'Piso 2'],
            ['nombre' => 'Aula 5', 'capacidad' => 25, 'ubicacion' => 'Piso 2'],
            ['nombre' => 'Aula 6', 'capacidad' => 30, 'ubicacion' => 'Piso 3'],
            ['nombre' => 'Aula 7', 'capacidad' => 22, 'ubicacion' => 'Piso 3'],
        ] as $datos) {
            Aula::query()->firstOrCreate(['nombre' => $datos['nombre']], $datos);
        }

        return Aula::query()->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function crearDocentes(int $cantidad): Collection
    {
        $creados = collect();

        for ($i = 0; $i < $cantidad; $i++) {
            $nombreCompleto = $this->nombreAleatorio().' '.$this->apellidosAleatorios();
            $email = Str::slug($nombreCompleto).'.docente'.$i.'@ceba.test';

            $docente = User::factory()->create([
                'name' => $nombreCompleto,
                'email' => $email,
                'dni' => (string) $this->secuenciaDniDocente++,
                'estado' => EstadoUsuarioEnum::ACTIVO,
            ]);
            $docente->assignRole(RolEnum::DOCENTE->value);

            $creados->push($docente);
        }

        return $creados;
    }

    /**
     * @param  Collection<int, Aula>  $aulas
     * @param  Collection<int, User>  $docentesNuevos
     */
    private function crearHorarios(Ciclo $ciclo, Collection $aulas, Collection $docentesNuevos): void
    {
        $docenteBase = User::query()->where('email', 'docente@ceba.test')->first();
        $docentes = $docenteBase ? $docentesNuevos->push($docenteBase) : $docentesNuevos;
        $docentes = $docentes->values();

        // Solo aulas de ESTE ciclo (o "sueltas", sin ciclo_id) -- una aula
        // ligada a otro Grupo no debe usarse aquí aunque esté libre, o el
        // horario quedaría invisible en la ocupación de este ciclo.
        $aulas = $aulas->filter(fn (Aula $aula) => $aula->ciclo_id === null || $aula->ciclo_id === $ciclo->id)->values();

        if ($docentes->isEmpty() || $aulas->isEmpty()) {
            return;
        }

        // Siempre una franja institucional completa (Lunes-Miércoles,
        // Martes-Jueves o Domingo), nunca un día suelto: de lo contrario el
        // horario queda huérfano en el listado ("Otros días") sin
        // corresponder a ningún turno real de la institución.
        $franjas = FranjaHorarioEnum::cases();
        $bloques = [['18:00:00', '20:00:00'], ['20:00:00', '22:00:00']];

        $ocupacionAula = [];
        $ocupacionDocente = [];

        foreach (Horario::query()->where('ciclo_id', $ciclo->id)->with('dias')->get() as $existente) {
            foreach ($existente->dias as $diaExistente) {
                $bloqueIdx = $diaExistente->hora_inicio === '18:00:00' ? 0 : 1;
                $ocupacionAula["{$existente->aula_id}|{$diaExistente->dia_semana->value}|{$bloqueIdx}"] = true;
                $ocupacionDocente["{$existente->docente_id}|{$diaExistente->dia_semana->value}|{$bloqueIdx}"] = true;
            }
        }

        $cursosSinHorario = Curso::query()
            ->whereDoesntHave('horarios', fn ($query) => $query->where('ciclo_id', $ciclo->id))
            ->get();

        $docenteIdx = 0;

        foreach ($cursosSinHorario as $curso) {
            $this->ubicarHorario($curso, $ciclo, $franjas, $bloques, $aulas, $docentes, $ocupacionAula, $ocupacionDocente, $docenteIdx);
        }
    }

    /**
     * Busca la primera franja/bloque/aula/docente libres (en ese orden) y
     * crea el Horario correspondiente para $curso, actualizando los mapas
     * de ocupación pasados por referencia. Devuelve false si no quedaba
     * ningún espacio disponible entre las franjas dadas.
     *
     * @param  list<FranjaHorarioEnum>  $franjas
     * @param  list<array{0: string, 1: string}>  $bloques
     * @param  Collection<int, Aula>  $aulas
     * @param  Collection<int, User>  $docentes
     * @param  array<string, bool>  $ocupacionAula
     * @param  array<string, bool>  $ocupacionDocente
     */
    private function ubicarHorario(
        Curso $curso,
        Ciclo $ciclo,
        array $franjas,
        array $bloques,
        Collection $aulas,
        Collection $docentes,
        array &$ocupacionAula,
        array &$ocupacionDocente,
        int &$docenteIdx,
    ): bool {
        // Las aulas "sueltas" (sin letra) sirven para cualquier grado; las
        // ligadas a un Grupo solo para el grado cuya letra coincide (ver
        // Grado::letraAula()).
        $letraDelGrado = $curso->grado->letraAula();
        $aulas = $aulas->filter(fn (Aula $aula) => $aula->letra === null || $aula->letra === $letraDelGrado)->values();

        foreach ($franjas as $franja) {
            $diasFranja = $franja->dias();

            foreach ($bloques as $bloqueIdx => $bloque) {
                foreach ($aulas as $aula) {
                    $aulaLibre = collect($diasFranja)->every(
                        fn (DiaSemanaEnum $dia) => ! isset($ocupacionAula["{$aula->id}|{$dia->value}|{$bloqueIdx}"])
                    );

                    if (! $aulaLibre) {
                        continue;
                    }

                    $docente = null;

                    for ($i = 0; $i < $docentes->count(); $i++) {
                        $candidato = $docentes[($docenteIdx + $i) % $docentes->count()];

                        $docenteLibre = collect($diasFranja)->every(
                            fn (DiaSemanaEnum $dia) => ! isset($ocupacionDocente["{$candidato->id}|{$dia->value}|{$bloqueIdx}"])
                        );

                        if ($docenteLibre) {
                            $docente = $candidato;
                            $docenteIdx = ($docenteIdx + $i + 1) % $docentes->count();
                            break;
                        }
                    }

                    if (! $docente) {
                        continue;
                    }

                    $horario = Horario::query()->create([
                        'curso_id' => $curso->id,
                        'docente_id' => $docente->id,
                        'aula_id' => $aula->id,
                        'ciclo_id' => $ciclo->id,
                        'grado_id' => $curso->grado_id,
                    ]);

                    foreach ($diasFranja as $dia) {
                        $horario->dias()->create([
                            'dia_semana' => $dia,
                            'hora_inicio' => $bloque[0],
                            'hora_fin' => $bloque[1],
                        ]);

                        $ocupacionAula["{$aula->id}|{$dia->value}|{$bloqueIdx}"] = true;
                        $ocupacionDocente["{$docente->id}|{$dia->value}|{$bloqueIdx}"] = true;
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Grado>  $grados
     */
    private function crearEstudiantesYMatriculas(Ciclo $ciclo, Collection $grados): void
    {
        $servicio = app(MatriculaService::class);

        foreach ($grados as $grado) {
            for ($i = 0; $i < 12; $i++) {
                // La edad ya no restringe a qué grado/horario se matricula
                // el estudiante -- los 4 grupos sirven a ambas edades, así
                // que este seeder alterna al azar entre ambos tipos de
                // estudiante para cada grado. Solo afecta su
                // fecha_fin_estudio calculada (6 u 8 meses).
                $esMenor = random_int(0, 1) === 1;
                $edad = $esMenor ? random_int(14, 17) : random_int(18, 52);

                $estudiante = $servicio->registrarEstudiante(new RegistrarEstudianteData(
                    nombres: $this->nombreAleatorio(),
                    apellidos: $this->apellidosAleatorios(),
                    dni: new Dni((string) $this->secuenciaDniEstudiante++),
                    fechaNacimiento: now()->subYears($edad)->subDays(random_int(0, 330))->format('Y-m-d'),
                    estadoCivil: $esMenor ? null : Arr::random(EstadoCivilEnum::cases()),
                    direccion: $this->direccionAleatoria(),
                    celular: new Telefono($this->siguienteCelular()),
                    observaciones: null,
                ));

                if ($esMenor) {
                    $servicio->registrarApoderado($estudiante, new RegistrarApoderadoData(
                        nombres: $this->nombreAleatorio().' '.$this->apellidosAleatorios(),
                        dni: new Dni((string) $this->secuenciaDniApoderado++),
                        celular: new Telefono($this->siguienteCelular()),
                        correo: null,
                        direccion: null,
                        parentesco: Arr::random(['Padre', 'Madre', 'Tutor legal']),
                    ));
                }

                try {
                    $servicio->matricular($estudiante, new RegistrarMatriculaData(
                        cicloId: $ciclo->id,
                        gradoId: $grado->id,
                        observaciones: null,
                        registradoPor: null,
                    ));
                } catch (ValidationException) {
                    // Si el periodo de matrícula del ciclo activo no está
                    // abierto hoy, no bloquea el resto del seeder: el
                    // estudiante queda registrado sin matrícula de ejemplo.
                }
            }
        }
    }

    /**
     * Un ciclo SIAGIE anual de ejemplo (independiente de los 4 grupos
     * rotativos) con un par de estudiantes matriculados, para poder
     * verificar la ficha/historial/wizard con un caso real de esa
     * modalidad.
     *
     * @param  Collection<int, Grado>  $grados
     */
    private function poblarSiagieAnual(Collection $grados): void
    {
        $anio = (int) now()->year;

        $cicloAnual = Ciclo::query()->firstOrCreate(
            ['modalidad' => ModalidadCicloEnum::ANUAL, 'anio' => $anio],
            [
                'nombre' => "SIAGIE Anual - {$anio}",
                'tipo' => null,
                'fecha_inicio' => "{$anio}-03-01",
                'fecha_fin' => "{$anio}-10-31",
                'estado' => EstadoCicloEnum::ACTIVO,
            ]
        );

        $servicio = app(MatriculaService::class);

        foreach ($grados->take(2) as $grado) {
            $estudiante = $servicio->registrarEstudiante(new RegistrarEstudianteData(
                nombres: $this->nombreAleatorio(),
                apellidos: $this->apellidosAleatorios(),
                dni: new Dni((string) $this->secuenciaDniEstudiante++),
                fechaNacimiento: now()->subYears(random_int(18, 40))->subDays(random_int(0, 330))->format('Y-m-d'),
                estadoCivil: Arr::random(EstadoCivilEnum::cases()),
                direccion: $this->direccionAleatoria(),
                celular: new Telefono($this->siguienteCelular()),
                observaciones: null,
            ));

            try {
                $servicio->matricular($estudiante, new RegistrarMatriculaData(
                    cicloId: $cicloAnual->id,
                    gradoId: $grado->id,
                    observaciones: null,
                    registradoPor: null,
                ));
            } catch (ValidationException) {
                // No bloquea el resto del seeder si ya existe la matrícula.
            }
        }
    }

    private function diversificarEstadosDeEstudiantes(): void
    {
        Estudiante::query()->inRandomOrder()->limit(3)->update(['estado' => 'retirado']);
        Estudiante::query()->where('estado', 'activo')->inRandomOrder()->limit(2)->update(['estado' => 'pausa']);
        Estudiante::query()->where('estado', 'activo')->inRandomOrder()->limit(2)->update(['estado' => 'egresado']);
    }

    /**
     * @param  Collection<int, Horario>  $horarios
     */
    private function poblarAulaVirtual(Collection $horarios): void
    {
        $cursoVirtualService = app(CursoVirtualService::class);
        $materialService = app(MaterialService::class);
        $tareaService = app(TareaService::class);
        $publicacionService = app(PublicacionService::class);
        $foroService = app(ForoService::class);
        $evaluacionService = app(EvaluacionService::class);

        foreach ($horarios as $horario) {
            $curso = $cursoVirtualService->activarParaHorario($horario);

            $materialService->crear($curso, TipoMaterialEnum::ENLACE, 'Guía de la unidad 1', 'https://example.com/guia-unidad-1', null);
            $materialService->crear($curso, TipoMaterialEnum::VIDEO, 'Videoclase introductoria', 'https://example.com/video-intro', null);

            $tarea = $tareaService->crear($curso, [
                'titulo' => 'Práctica calificada 1',
                'descripcion' => 'Resolver los ejercicios propuestos en clase.',
                'fecha_limite' => now()->addWeek(),
                'puntaje_max' => 20,
            ]);

            $publicacionService->crear($curso, $horario->docente_id, TipoPublicacionEnum::ANUNCIO, 'Bienvenidos al curso. Revisen los materiales antes de la próxima clase.');
            $foroService->crear($curso, $horario->docente_id, 'Dudas sobre la unidad 1', 'Usen este espacio para consultar cualquier duda sobre los temas de la primera unidad.');

            $estudiantes = $evaluacionService->estudiantesDelHorario($horario);

            foreach ($estudiantes as $indice => $estudiante) {
                $entrega = $tareaService->entregar($tarea, $estudiante, 'Adjunto mi trabajo.', null);

                if ($indice % 2 === 0) {
                    $tareaService->calificar($entrega, (float) random_int(12, 20));
                }
            }
        }
    }

    /**
     * @param  Collection<int, Horario>  $horarios
     */
    private function registrarAsistencia(Collection $horarios): void
    {
        $service = app(AsistenciaService::class);

        $estados = [
            EstadoAsistenciaEnum::PRESENTE, EstadoAsistenciaEnum::PRESENTE, EstadoAsistenciaEnum::PRESENTE,
            EstadoAsistenciaEnum::PRESENTE, EstadoAsistenciaEnum::PRESENTE, EstadoAsistenciaEnum::PRESENTE,
            EstadoAsistenciaEnum::PRESENTE,
            EstadoAsistenciaEnum::TARDANZA,
            EstadoAsistenciaEnum::FALTA,
            EstadoAsistenciaEnum::JUSTIFICADO,
        ];

        foreach ($horarios as $horario) {
            $estudiantes = $service->estudiantesDelHorario($horario);

            if ($estudiantes->isEmpty()) {
                continue;
            }

            for ($semanasAtras = 3; $semanasAtras >= 0; $semanasAtras--) {
                $fecha = now()->subWeeks($semanasAtras)->format('Y-m-d');
                $registros = [];

                foreach ($estudiantes as $estudiante) {
                    $registros[$estudiante->id] = Arr::random($estados)->value;
                }

                $service->registrar($horario, $fecha, $registros);
            }
        }
    }

    /**
     * @param  Collection<int, Horario>  $horarios
     */
    private function registrarEvaluacionesYLibretas(Ciclo $ciclo, Collection $horarios): void
    {
        $service = app(EvaluacionService::class);
        $libretaService = app(LibretaService::class);

        foreach ($horarios as $indice => $horario) {
            $estudiantes = $service->estudiantesDelHorario($horario);

            if ($estudiantes->isEmpty()) {
                continue;
            }

            $evaluacionUno = $service->crear($horario, 'Evaluación mensual 1', now()->subWeeks(3)->format('Y-m-d'));
            $evaluacionDos = $service->crear($horario, 'Evaluación mensual 2', now()->subWeek()->format('Y-m-d'));

            foreach ($estudiantes as $estudiante) {
                $service->calificar($evaluacionUno, $estudiante, (float) random_int(8, 20), null, $horario->docente_id);
                $service->calificar($evaluacionDos, $estudiante, (float) random_int(8, 20), null, $horario->docente_id);
            }

            $service->publicar($evaluacionUno);

            // Deja una fracción de las evaluaciones más recientes sin
            // publicar, para mostrar el flujo "docente registra →
            // coordinador publica" también en los datos de ejemplo.
            if ($indice % 3 !== 0) {
                $service->publicar($evaluacionDos);
            }
        }

        Estudiante::query()
            ->whereHas('matriculas', fn ($query) => $query->where('ciclo_id', $ciclo->id)->where('estado', 'aprobada'))
            ->inRandomOrder()
            ->limit(3)
            ->get()
            ->each(fn (Estudiante $estudiante) => $libretaService->generar($estudiante, $ciclo));
    }

    /**
     * Conceptos, cuentas bancarias, planes de cuotas y pagos en distintos
     * estados (al día, pendiente de aprobación, rechazado, vencido) para
     * que la cola de Tesorería y "Mi estado de cuenta" no se vean vacíos.
     * No adjunta QR a las cuentas bancarias: el seeder no genera imágenes,
     * solo datos transaccionales.
     */
    private function poblarPagos(): void
    {
        $conceptoService = app(ConceptoPagoService::class);
        $cuentaService = app(CuentaBancariaService::class);
        $planService = app(PlanPagoService::class);
        $pagoService = app(PagoService::class);
        $bloqueoService = app(BloqueoAccesoService::class);

        $conceptoMensualidad = $conceptoService->crear('Mensualidad', TipoConceptoEnum::MENSUALIDAD, 100.00);
        $conceptoService->crear('Matrícula', TipoConceptoEnum::MATRICULA, 80.00);
        $conceptoService->crear('Certificado de estudios', TipoConceptoEnum::CERTIFICADO, 60.00);
        $conceptoService->crear('Constancia de matrícula', TipoConceptoEnum::CONSTANCIA, 20.00);
        $conceptoService->crear('Penalidad por atraso', TipoConceptoEnum::PENALIDAD, 15.00);
        $conceptoService->crear('Otros', TipoConceptoEnum::OTRO, 0.00);

        $cuentaService->crear(MedioCuentaEnum::BANCO, 'BCP', '194-2345678-0-12', '00219400234567801277', null, null, 'CEBA E.I.R.L.', null);
        $cuentaService->crear(MedioCuentaEnum::BANCO, 'Interbank', '898-3012345678-9', '00389800301234567899', null, null, 'CEBA E.I.R.L.', null);
        $cuentaService->crear(MedioCuentaEnum::BILLETERA, null, null, null, TipoBilleteraEnum::YAPE, '987654321', 'CEBA E.I.R.L.', null);
        $cuentaService->crear(MedioCuentaEnum::BILLETERA, null, null, null, TipoBilleteraEnum::PLIN, '912345678', 'CEBA E.I.R.L.', null);

        $registradorId = User::query()->where('email', 'coordinador@ceba.test')->value('id');
        $aprobadorId = User::query()->where('email', 'tesoreria@ceba.test')->value('id');

        $matriculas = Matricula::query()->where('estado', 'aprobada')->with('estudiante')->get();

        foreach ($matriculas as $indice => $matricula) {
            $numeroCuotas = match (true) {
                $indice % 7 === 0 => NumeroCuotasEnum::OCHO,
                $indice % 5 === 0 => NumeroCuotasEnum::UNA,
                default => NumeroCuotasEnum::SEIS,
            };

            try {
                $plan = $planService->crear($matricula, $numeroCuotas, 100.0 * $numeroCuotas->value);
            } catch (ValidationException) {
                continue;
            }

            $cuotas = $plan->cuotas()->orderBy('numero')->get();

            // Escenario de deuda por estudiante: 0 y 3 se adelantan dos
            // cuotas al pasado (3 dispara el bloqueo si quedan sin pagar);
            // 1, 2 y 4 solo adelantan una.
            $escenario = $indice % 5;
            $numVencidas = in_array($escenario, [0, 3], true) ? min(2, $cuotas->count()) : min(1, $cuotas->count());

            for ($i = 0; $i < $numVencidas; $i++) {
                $cuotas[$i]->update(['fecha_vencimiento' => now()->subMonths($numVencidas - $i)]);
            }

            $cuotasVencidas = $cuotas->take($numVencidas);

            match ($escenario) {
                0 => $cuotasVencidas->each(fn (Cuota $cuota) => $this->pagarCuota($pagoService, $matricula->estudiante, $conceptoMensualidad, $cuota, $registradorId, $aprobadorId)),
                1 => $this->dejarPagoPendiente($pagoService, $matricula->estudiante, $conceptoMensualidad, $cuotasVencidas->first(), $registradorId),
                4 => $this->rechazarPago($pagoService, $matricula->estudiante, $conceptoMensualidad, $cuotasVencidas->first(), $registradorId, $aprobadorId),
                // 2 y 3 quedan vencidas sin ningún pago registrado.
                default => null,
            };

            $bloqueoService->evaluarYDesbloquear($matricula->estudiante);
        }
    }

    private function pagarCuota(PagoService $service, Estudiante $estudiante, ConceptoPago $concepto, Cuota $cuota, ?int $registradoPor, ?int $aprobadoPor): void
    {
        $pago = $service->registrar($estudiante, $concepto, [['monto' => (float) $cuota->monto, 'metodo' => MetodoPagoEnum::YAPE->value]], $cuota, null, $registradoPor);

        if ($aprobadoPor) {
            $service->aprobar($pago, $aprobadoPor, SerieReciboEnum::ORIGINAL);
        }
    }

    private function dejarPagoPendiente(PagoService $service, Estudiante $estudiante, ConceptoPago $concepto, ?Cuota $cuota, ?int $registradoPor): void
    {
        if (! $cuota) {
            return;
        }

        $service->registrar($estudiante, $concepto, [['monto' => (float) $cuota->monto, 'metodo' => MetodoPagoEnum::TRANSFERENCIA->value]], $cuota, null, $registradoPor);
    }

    private function rechazarPago(PagoService $service, Estudiante $estudiante, ConceptoPago $concepto, ?Cuota $cuota, ?int $registradoPor, ?int $aprobadoPor): void
    {
        if (! $cuota || ! $aprobadoPor) {
            return;
        }

        $pago = $service->registrar($estudiante, $concepto, [['monto' => (float) $cuota->monto, 'metodo' => MetodoPagoEnum::EFECTIVO->value]], $cuota, null, $registradoPor);
        $service->rechazar($pago, $aprobadoPor, 'El comprobante no corresponde al monto de la cuota.');
    }

    private function poblarCertificados(): void
    {
        $certificadoService = app(CertificadoService::class);

        $coordinador = User::query()->where('email', 'coordinador@ceba.test')->first();

        if (! $coordinador) {
            return;
        }

        $matriculas = Matricula::query()
            ->where('estado', 'aprobada')
            ->with('estudiante')
            ->inRandomOrder()
            ->limit(6)
            ->get();

        foreach ($matriculas as $indice => $matricula) {
            match ($indice % 4) {
                // 0: certificado emitido directamente, con un duplicado posterior.
                0 => tap(
                    $certificadoService->emitir($matricula->estudiante, $matricula, null, null, $coordinador),
                    fn ($certificado) => $certificadoService->duplicar($certificado, 'Extravío del documento original.', $coordinador),
                ),
                // 1: solicitud atendida (emitida a partir de una solicitud del estudiante).
                1 => $certificadoService->emitir(
                    $matricula->estudiante,
                    $matricula,
                    $certificadoService->solicitar($matricula->estudiante, $matricula, 'Trámite de continuación de estudios.'),
                    null,
                    $coordinador,
                ),
                // 2: solicitud pendiente, todavía sin atender.
                2 => $certificadoService->solicitar($matricula->estudiante, $matricula, 'Trámite laboral.'),
                // 3: solicitud rechazada.
                3 => $certificadoService->rechazarSolicitud(
                    $certificadoService->solicitar($matricula->estudiante, $matricula, 'Beca de estudios superiores.'),
                    'El estudiante registra deuda pendiente con la institución.',
                    $coordinador,
                ),
                default => null,
            };
        }
    }

    private function poblarIncidencias(): void
    {
        $incidenciaService = app(IncidenciaService::class);

        $reportante = User::query()->where('email', 'coordinador@ceba.test')->first()
            ?? User::query()->where('email', 'docente@ceba.test')->first();

        if (! $reportante) {
            return;
        }

        $descripcionesConducta = [
            'Interrumpió reiteradamente la clase con conversaciones ajenas al tema.',
            'Usó el celular durante la evaluación sin autorización del docente.',
            'Falta de respeto hacia un compañero durante el receso.',
        ];

        $descripcionesDisciplina = [
            'Llegó 40 minutos tarde sin justificación.',
            'Se retiró del aula antes de que terminara la sesión sin avisar al docente.',
            'Tres inasistencias consecutivas sin comunicación previa a la institución.',
        ];

        $matriculas = Matricula::query()
            ->where('estado', 'aprobada')
            ->with('estudiante')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        foreach ($matriculas as $indice => $matricula) {
            $estudiante = $matricula->estudiante;
            $tipo = TipoIncidenciaEnum::cases()[$indice % 4];

            $horariosDelEstudiante = Horario::query()
                ->where('grado_id', $matricula->grado_id)
                ->where('ciclo_id', $matricula->ciclo_id)
                ->pluck('id');

            $horario = null;
            $tarea = null;
            $evaluacion = null;
            $descripcion = null;

            if ($tipo === TipoIncidenciaEnum::CONDUCTA) {
                $descripcion = Arr::random($descripcionesConducta);
            } elseif ($tipo === TipoIncidenciaEnum::DISCIPLINA) {
                $descripcion = Arr::random($descripcionesDisciplina);
            } elseif ($tipo === TipoIncidenciaEnum::TAREA_NO_REALIZADA) {
                // Preferir una tarea realmente vencida sin entregar; en la
                // demo casi todas tienen plazo a futuro, así que si no hay
                // ninguna vencida se usa cualquier tarea del estudiante --
                // sigue siendo un dato coherente, solo no "vencido" de verdad.
                $tarea = $incidenciaService->tareasNoRealizadasDe($estudiante)->first()
                    ?? Tarea::query()
                        ->whereIn('curso_virtual_id', CursoVirtual::query()->whereIn('horario_id', $horariosDelEstudiante)->pluck('id'))
                        ->inRandomOrder()
                        ->first();

                if ($tarea) {
                    $descripcion = "No entregó la tarea «{$tarea->titulo}» dentro del plazo.";
                }
            } elseif ($tipo === TipoIncidenciaEnum::EVALUACION_NO_REALIZADA) {
                $evaluacion = $incidenciaService->evaluacionesNoRealizadasDe($estudiante)->first()
                    ?? Evaluacion::query()->whereIn('horario_id', $horariosDelEstudiante)->inRandomOrder()->first();

                if ($evaluacion) {
                    $horario = $evaluacion->horario;
                    $descripcion = "No rindió la evaluación «{$evaluacion->nombre}».";
                }
            }

            if ($descripcion === null) {
                // Este estudiante no tiene ninguna tarea/evaluación con qué
                // vincular la incidencia todavía: se salta en vez de forzar
                // un dato huérfano.
                continue;
            }

            $incidenciaService->crear(
                $estudiante,
                $reportante,
                $tipo,
                $descripcion,
                now()->subDays(random_int(0, 25))->format('Y-m-d'),
                $horario,
                $tarea,
                $evaluacion,
            );
        }
    }

    private function poblarNotificaciones(): void
    {
        $plantillaService = app(PlantillaService::class);
        $campaniaService = app(CampaniaWhatsappService::class);

        $coordinador = User::query()->where('email', 'coordinador@ceba.test')->first();

        if (! $coordinador) {
            return;
        }

        $bienvenida = $plantillaService->crear(
            'Bienvenida de ciclo',
            'Hola {{nombre}}, te damos la bienvenida al nuevo ciclo en CEBA. ¡Éxitos!',
            $coordinador,
        );
        $plantillaService->crear(
            'Recordatorio de cuota',
            'Hola {{nombre}}, recuerda que tienes una cuota próxima a vencer. Evita el bloqueo de tu libreta regularizando a tiempo.',
            $coordinador,
        );
        $plantillaService->crear(
            'Aviso general',
            'Hola {{nombre}}, te recordamos revisar el Aula Virtual: hay tareas y publicaciones nuevas esta semana.',
            $coordinador,
        );

        try {
            $campaniaService->crearYEnviar('Bienvenida del ciclo activo', $bienvenida, [], $coordinador);
        } catch (ValidationException) {
            // Sin destinatarios con teléfono de contacto: se omite la campaña demo.
        }

        app(RecordatorioCuotaService::class)->enviarRecordatorios();
    }

    private function nombreAleatorio(): string
    {
        return Arr::random(array_merge($this->nombresMasculinos, $this->nombresFemeninos));
    }

    private function apellidosAleatorios(): string
    {
        return Arr::random($this->apellidos).' '.Arr::random($this->apellidos);
    }

    private function direccionAleatoria(): string
    {
        $vias = ['Jr.', 'Av.', 'Calle', 'Psje.'];
        $nombres = ['Los Álamos', 'Las Flores', 'San Martín', 'Túpac Amaru', 'Los Pinos', 'La Cultura', 'Grau', 'Bolognesi'];

        return Arr::random($vias).' '.Arr::random($nombres).' '.random_int(100, 899);
    }

    private function siguienteCelular(): string
    {
        $numero = sprintf('9%08d', $this->secuenciaCelular);
        $this->secuenciaCelular++;

        return $numero;
    }
}
