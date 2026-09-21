<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Services;

use App\Models\User;
use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Models\CursoCapacitacion;
use App\Modules\Certificados\Models\PlantillaCertificado;
use App\Modules\Certificados\Models\SolicitudCertificado;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Services\NotificacionService;
use App\Shared\Enums\MetodoEntregaEnum;
use App\Shared\ValueObjects\Dni;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CertificadoService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly LibretaService $libretas,
        private readonly MatriculaService $matricula,
    ) {}

    /**
     * @param  list<UploadedFile>  $requisitos
     */
    public function solicitar(
        Estudiante $estudiante,
        ?Matricula $matricula,
        string $motivo,
        array $requisitos = [],
        TipoDocumentoEnum $tipo = TipoDocumentoEnum::CERTIFICADO_ESTUDIOS,
        ?MetodoEntregaEnum $metodoEntrega = null,
        ?string $correoEntrega = null,
    ): SolicitudCertificado {
        /** @var SolicitudCertificado $solicitud */
        $solicitud = SolicitudCertificado::query()->create([
            'estudiante_id' => $estudiante->id,
            'tipo' => $tipo,
            'matricula_id' => $matricula?->id,
            'motivo' => $motivo,
            'estado' => EstadoSolicitudCertificadoEnum::PENDIENTE,
            'metodo_entrega' => $metodoEntrega,
            'correo_entrega' => $metodoEntrega?->requiereCorreo() === true ? $correoEntrega : null,
        ]);

        foreach ($requisitos as $archivo) {
            $solicitud->addMedia($archivo)->toMediaCollection('requisitos');
        }

        return $solicitud;
    }

    public function emitir(
        Estudiante $estudiante,
        ?Matricula $matricula,
        ?SolicitudCertificado $solicitud,
        ?string $observaciones,
        User $emisor,
        TipoDocumentoEnum $tipo = TipoDocumentoEnum::CERTIFICADO_ESTUDIOS,
        ?CursoCapacitacion $cursoCapacitacion = null,
        ?string $numeroRegistro = null,
        ?float $nota = null,
    ): Certificado {
        $tipo = $solicitud !== null ? $solicitud->tipo : $tipo;

        return DB::transaction(function () use ($estudiante, $matricula, $solicitud, $observaciones, $emisor, $tipo, $cursoCapacitacion, $numeroRegistro, $nota) {
            $certificado = $this->crearConNumeroUnico([
                'estudiante_id' => $estudiante->id,
                'tipo' => $tipo,
                'matricula_id' => $matricula?->id,
                'curso_capacitacion_id' => $cursoCapacitacion?->id,
                'numero_registro' => $numeroRegistro,
                'nota' => $nota,
                'codigo_verificacion' => $this->generarCodigoVerificacion(),
                'es_duplicado' => false,
                'emitido_por' => $emisor->id,
                'fecha_emision' => now(),
                'observaciones' => $observaciones,
                'metodo_entrega' => $solicitud?->metodo_entrega,
                'correo_entrega' => $solicitud?->correo_entrega,
            ]);

            $this->generarPdf($certificado);

            if ($solicitud) {
                $solicitud->update([
                    'estado' => EstadoSolicitudCertificadoEnum::ATENDIDA,
                    'atendido_por' => $emisor->id,
                    'certificado_id' => $certificado->id,
                ]);
            }

            if ($estudiante->user) {
                $this->notificaciones->notificar(
                    $estudiante->user,
                    TipoNotificacionEnum::CERTIFICADO_LISTO,
                    "Tu {$tipo->label()} está listo para recoger.",
                    $this->rutaMisDocumentos($tipo),
                );
            }

            return $certificado;
        });
    }

    /**
     * Crea el Certificado reintentando ante una colisión real de `numero`
     * (columna única) en vez de dejar que reviente con un QueryException
     * crudo: bajo concurrencia, dos emisiones casi simultáneas pueden leer
     * el mismo conteo en siguienteNumero() antes de que la primera
     * confirme su INSERT. El unique() de la columna es la garantía real
     * (nunca quedan dos certificados con el mismo número); el reintento
     * solo evita que ese caso, poco frecuente, se vea como un error.
     *
     * @param  array<string, mixed>  $atributos  Sin la clave 'numero': la agrega este método.
     */
    private function crearConNumeroUnico(array $atributos): Certificado
    {
        $intentosRestantes = 5;

        while (true) {
            try {
                /** @var Certificado $certificado */
                $certificado = Certificado::query()->create([...$atributos, 'numero' => $this->siguienteNumero()]);

                return $certificado;
            } catch (UniqueConstraintViolationException $e) {
                if (--$intentosRestantes <= 0) {
                    throw $e;
                }
            }
        }
    }

    /**
     * "Mis certificados" y "Mis constancias" son dos pantallas separadas: la
     * notificación debe apuntar a la que corresponda según el tipo emitido.
     */
    private function rutaMisDocumentos(TipoDocumentoEnum $tipo): string
    {
        return $tipo->esConstancia() ? route('constancias.mis-constancias') : route('certificados.mis-certificados');
    }

    /**
     * La libreta de notas se genera distinto (un resumen de promedios, no
     * una plantilla de texto) pero comparte el mismo flujo de solicitud,
     * revisión, notificación y entrega que los demás documentos.
     */
    public function emitirLibretaDesdeSolicitud(SolicitudCertificado $solicitud, User $emisor): Libreta
    {
        if ($solicitud->matricula === null) {
            throw ValidationException::withMessages([
                'matricula' => 'La solicitud de libreta debe estar vinculada a una matrícula.',
            ]);
        }

        $libreta = $this->libretas->generar($solicitud->estudiante, $solicitud->matricula->ciclo);

        $libreta->update([
            'metodo_entrega' => $solicitud->metodo_entrega,
            'correo_entrega' => $solicitud->correo_entrega,
        ]);

        $solicitud->update([
            'estado' => EstadoSolicitudCertificadoEnum::ATENDIDA,
            'atendido_por' => $emisor->id,
            'libreta_id' => $libreta->id,
        ]);

        if ($solicitud->estudiante->user) {
            $this->notificaciones->notificar(
                $solicitud->estudiante->user,
                TipoNotificacionEnum::CERTIFICADO_LISTO,
                'Tu libreta de notas está lista para recoger.',
                route('certificados.mis-certificados'),
            );
        }

        return $libreta;
    }

    /**
     * Registra que el estudiante recogió el documento en persona (con la
     * foto como constancia) o que se le envió por correo: la prueba de
     * "entregado conforme" que cierra el flujo.
     */
    public function marcarEntregado(Certificado $certificado, User $entregadoPor, ?UploadedFile $foto = null): Certificado
    {
        if ($certificado->entregado_en !== null) {
            throw ValidationException::withMessages([
                'entrega' => 'Este documento ya fue marcado como entregado.',
            ]);
        }

        $certificado->update([
            'entregado_en' => now(),
            'entregado_por' => $entregadoPor->id,
        ]);

        if ($foto) {
            $certificado->addMedia($foto)->toMediaCollection('foto_entrega');
        }

        return $certificado;
    }

    /**
     * La misma constancia de entrega que marcarEntregado(), para una
     * libreta de notas en vez de un certificado/constancia.
     */
    public function marcarLibretaEntregada(Libreta $libreta, User $entregadoPor, ?UploadedFile $foto = null): Libreta
    {
        if ($libreta->entregado_en !== null) {
            throw ValidationException::withMessages([
                'entrega' => 'Esta libreta ya fue marcada como entregada.',
            ]);
        }

        $libreta->update([
            'entregado_en' => now(),
            'entregado_por' => $entregadoPor->id,
        ]);

        if ($foto) {
            $libreta->addMedia($foto)->toMediaCollection('foto_entrega');
        }

        return $libreta;
    }

    public function duplicar(Certificado $original, ?string $observaciones, User $emisor): Certificado
    {
        $base = $original->es_duplicado ? $original->original : $original;

        return DB::transaction(function () use ($base, $observaciones, $emisor) {
            $duplicado = $this->crearConNumeroUnico([
                'estudiante_id' => $base->estudiante_id,
                'tipo' => $base->tipo,
                'matricula_id' => $base->matricula_id,
                'curso_capacitacion_id' => $base->curso_capacitacion_id,
                'numero_registro' => $base->numero_registro,
                'nota' => $base->nota,
                'codigo_verificacion' => $base->codigo_verificacion,
                'es_duplicado' => true,
                'certificado_original_id' => $base->id,
                'emitido_por' => $emisor->id,
                'fecha_emision' => now(),
                'observaciones' => $observaciones,
            ]);

            $this->generarPdf($duplicado);

            return $duplicado;
        });
    }

    /**
     * Emisión masiva de certificados de capacitación desde un CSV/Excel:
     * columnas dni, nombres, apellidos, numero_de_registro, nombre_del_curso,
     * horas_lectivas, documento_de_autorizacion, nota (opcional; 0-20)
     * (encabezados tal como se muestran en la UI de importación --
     * WithHeadingRow los normaliza a minúsculas y sin tildes). nombres/apellidos son solo de referencia
     * para quien arma el archivo: el estudiante se busca por dni, igual que
     * EvaluacionService::calificarDesdeFilas(). El curso se busca por
     * nombre_del_curso y se crea si no existe todavía (usando
     * horas_lectivas/documento_de_autorizacion de esa fila); si ya existe,
     * no se sobrescribe con lo que traiga la fila -- evita que un typo en
     * una fila corrompa el catálogo. Cada fila se procesa de forma
     * independiente: una fila inválida no afecta a las demás.
     *
     * @param  SupportCollection<int, SupportCollection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function emitirCapacitacionDesdeFilas(SupportCollection $filas, User $emisor): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                $dni = trim((string) ($fila->get('dni') ?? ''));

                if ($dni === '') {
                    throw new InvalidArgumentException('La columna «dni» es obligatoria.');
                }

                $estudiante = Estudiante::query()->where('dni', $dni)->first();

                if (! $estudiante) {
                    throw new InvalidArgumentException("No hay ningún estudiante registrado con el DNI {$dni}.");
                }

                $numeroRegistro = trim((string) ($fila->get('numero_de_registro') ?? ''));

                if ($numeroRegistro === '') {
                    throw new InvalidArgumentException('La columna «numero_de_registro» es obligatoria.');
                }

                if (Certificado::query()->where('numero_registro', $numeroRegistro)->exists()) {
                    throw new InvalidArgumentException("Ya existe un certificado con el número de registro {$numeroRegistro}.");
                }

                $nombreCurso = trim((string) ($fila->get('nombre_del_curso') ?? ''));

                if ($nombreCurso === '') {
                    throw new InvalidArgumentException('La columna «nombre_del_curso» es obligatoria.');
                }

                $curso = CursoCapacitacion::query()->where('nombre', $nombreCurso)->first();

                if (! $curso) {
                    $horasLectivas = (int) ($fila->get('horas_lectivas') ?? 0);

                    if ($horasLectivas < 1) {
                        throw new InvalidArgumentException("El curso «{$nombreCurso}» no existe todavía: agrega «horas_lectivas» en esta fila para crearlo.");
                    }

                    $documentoAutorizacion = trim((string) ($fila->get('documento_de_autorizacion') ?? ''));

                    $curso = CursoCapacitacion::query()->create([
                        'nombre' => $nombreCurso,
                        'horas_lectivas' => $horasLectivas,
                        'documento_autorizacion' => $documentoAutorizacion !== '' ? $documentoAutorizacion : null,
                    ]);
                }

                $notaValor = $fila->get('nota');
                $nota = null;

                if ($notaValor !== null && trim((string) $notaValor) !== '') {
                    if (! is_numeric($notaValor)) {
                        throw new InvalidArgumentException("La nota «{$notaValor}» no es un número válido.");
                    }

                    $nota = (float) $notaValor;

                    if ($nota < 0 || $nota > 20) {
                        throw new InvalidArgumentException('La nota debe estar entre 0 y 20.');
                    }
                }

                $this->emitir($estudiante, null, null, null, $emisor, TipoDocumentoEnum::CERTIFICADO_CAPACITACION, $curso, $numeroRegistro, $nota);

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $e->getMessage()];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    /**
     * Primer paso del "formato del cliente" (Excel con nombres y apellidos
     * en una sola columna, sin que el estudiante exista todavía): arma una
     * previsualización editable, sin guardar nada -- ni el estudiante ni el
     * certificado se crean aquí. El nombre completo se separa con una regla
     * fija (las últimas 2 palabras son apellidos) porque es la convención
     * más común, pero el cliente mezcla el orden en algunas filas (ver
     * bitácora), así que el resultado se muestra en pantalla para que el
     * usuario corrija a mano antes de confirmar.
     *
     * @param  SupportCollection<int, SupportCollection<string, mixed>>  $filas
     * @return list<array{fila: int, dni: string, nombres: string, apellidos: string, numero_registro: string, curso: string, horas_lectivas: int, documento_autorizacion: ?string, nota: ?float, estudiante_existe: bool}>
     */
    public function previsualizarImportacionCapacitacionFormatoCliente(SupportCollection $filas): array
    {
        $preview = [];

        foreach ($filas as $indice => $fila) {
            $dniCrudo = trim((string) ($fila->get('dni') ?? ''));

            if ($dniCrudo === '') {
                continue;
            }

            $dni = $this->normalizarDni($dniCrudo);

            $nombreCompleto = trim((string) (
                $fila->get('nombres_y_apellidos')
                ?? $fila->get('nombres_apellidos')
                ?? $fila->get('nombre_completo')
                ?? ''
            ));

            [$nombres, $apellidos] = $this->dividirNombreCompleto($nombreCompleto);

            $numeroRegistro = trim((string) (
                $fila->get('num_registro')
                ?? $fila->get('numero_registro')
                ?? $fila->get('num_de_registro')
                ?? $fila->get('numero_de_registro')
                ?? ''
            ));

            $documento = trim((string) ($fila->get('documento') ?? $fila->get('documento_de_autorizacion') ?? ''));
            $notaValor = $fila->get('nota');

            $preview[] = [
                'fila' => $indice + 2,
                'dni' => $dni,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'numero_registro' => $numeroRegistro,
                'curso' => trim((string) ($fila->get('curso') ?? '')),
                'horas_lectivas' => (int) ($fila->get('horas') ?? $fila->get('horas_lectivas') ?? 0),
                'documento_autorizacion' => $documento !== '' ? $documento : null,
                'nota' => ($notaValor !== null && trim((string) $notaValor) !== '' && is_numeric($notaValor)) ? (float) $notaValor : null,
                'estudiante_existe' => Estudiante::query()->where('dni', $dni)->exists(),
            ];
        }

        return $preview;
    }

    /**
     * Segundo paso: recibe las filas de la previsualización (con las
     * correcciones manuales que el usuario haya hecho en pantalla) y recién
     * ahí crea lo que falte. Si el DNI ya existe, reutiliza ese estudiante
     * tal cual está (no le pisa el nombre); si no existe, lo crea sin fecha
     * de nacimiento (dato opcional, ver MatriculaService::esMenorDeEdad()).
     * Cada fila en su propia transacción, igual que emitirCapacitacionDesdeFilas().
     *
     * @param  list<array{fila?: int, dni: string, nombres: string, apellidos: string, numero_registro: string, curso: string, horas_lectivas: int, documento_autorizacion: ?string, nota: ?float}>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function confirmarImportacionCapacitacionFormatoCliente(array $filas, User $emisor): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                DB::transaction(function () use ($fila, $emisor): void {
                    $dni = trim($fila['dni']);

                    if ($dni === '') {
                        throw new InvalidArgumentException('El DNI es obligatorio.');
                    }

                    $numeroRegistro = trim($fila['numero_registro']);

                    if ($numeroRegistro === '') {
                        throw new InvalidArgumentException('El número de registro es obligatorio.');
                    }

                    if (Certificado::query()->where('numero_registro', $numeroRegistro)->exists()) {
                        throw new InvalidArgumentException("Ya existe un certificado con el número de registro {$numeroRegistro}.");
                    }

                    $estudiante = Estudiante::query()->where('dni', $dni)->first();

                    if (! $estudiante) {
                        $nombres = trim($fila['nombres']);
                        $apellidos = trim($fila['apellidos']);

                        if ($nombres === '' || $apellidos === '') {
                            throw new InvalidArgumentException('Nombres y apellidos son obligatorios para crear al estudiante.');
                        }

                        $estudiante = $this->matricula->registrarEstudiante(new RegistrarEstudianteData(
                            nombres: $nombres,
                            apellidos: $apellidos,
                            dni: new Dni($dni),
                            fechaNacimiento: null,
                            estadoCivil: null,
                            direccion: null,
                            celular: null,
                            observaciones: null,
                        ));
                    }

                    $nombreCurso = trim($fila['curso']);

                    if ($nombreCurso === '') {
                        throw new InvalidArgumentException('El nombre del curso es obligatorio.');
                    }

                    $curso = CursoCapacitacion::query()->where('nombre', $nombreCurso)->first();

                    if (! $curso) {
                        $horasLectivas = $fila['horas_lectivas'];

                        if ($horasLectivas < 1) {
                            throw new InvalidArgumentException("El curso «{$nombreCurso}» no existe todavía: faltan las horas lectivas para crearlo.");
                        }

                        $curso = CursoCapacitacion::query()->create([
                            'nombre' => $nombreCurso,
                            'horas_lectivas' => $horasLectivas,
                            'documento_autorizacion' => $fila['documento_autorizacion'] ?? null,
                        ]);
                    }

                    $this->emitir(
                        $estudiante,
                        null,
                        null,
                        null,
                        $emisor,
                        TipoDocumentoEnum::CERTIFICADO_CAPACITACION,
                        $curso,
                        $numeroRegistro,
                        $fila['nota'] ?? null,
                    );
                });

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $fila['fila'] ?? ($indice + 1), 'mensaje' => $e->getMessage()];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    /**
     * DNI peruano de 8 dígitos que llega recortado (p. ej. una celda de
     * Excel con formato numérico se come los ceros a la izquierda): si
     * queda corto y es solo dígitos, se rellena con ceros adelante. No se
     * toca si ya tiene 8+ caracteres o trae letras (carné de extranjería).
     */
    private function normalizarDni(string $dni): string
    {
        $dni = trim($dni);

        if (strlen($dni) < 8 && ctype_digit($dni)) {
            return str_pad($dni, 8, '0', STR_PAD_LEFT);
        }

        return $dni;
    }

    /**
     * Regla estándar peruana: las últimas 2 palabras son los apellidos
     * (paterno + materno), el resto son nombres. Es solo la mejor apuesta
     * posible sin más contexto -- el cliente a veces manda el nombre
     * completo al revés (apellidos primero), y eso esta función no lo
     * puede detectar; por eso el resultado se revisa en pantalla antes de
     * confirmar, nunca se guarda directo.
     *
     * @return array{0: string, 1: string} [nombres, apellidos]
     */
    private function dividirNombreCompleto(string $nombreCompleto): array
    {
        $palabras = preg_split('/\s+/', trim($nombreCompleto), -1, PREG_SPLIT_NO_EMPTY);

        if ($palabras === [] || $palabras === false) {
            return ['', ''];
        }

        if (count($palabras) === 1) {
            return [$palabras[0], ''];
        }

        $apellidos = implode(' ', array_slice($palabras, -2));
        $nombres = implode(' ', array_slice($palabras, 0, -2));

        if ($nombres === '') {
            $nombres = $palabras[0];
            $apellidos = implode(' ', array_slice($palabras, 1));
        }

        return [$nombres, $apellidos];
    }

    public function rechazarSolicitud(SolicitudCertificado $solicitud, string $motivo, User $revisor): void
    {
        $this->validarPendiente($solicitud);

        $solicitud->update([
            'estado' => EstadoSolicitudCertificadoEnum::RECHAZADA,
            'atendido_por' => $revisor->id,
            'motivo_rechazo' => $motivo,
        ]);
    }

    public function plantillaParaTipo(TipoDocumentoEnum $tipo): PlantillaCertificado
    {
        return PlantillaCertificado::paraTipo($tipo);
    }

    /**
     * @param  array{institucion: string, titulo: string, cuerpo: string, pie_nota: ?string, codigo_documento_aprobacion: ?string, color_acento: string}  $datos
     */
    public function guardarPlantilla(TipoDocumentoEnum $tipo, array $datos): PlantillaCertificado
    {
        $plantilla = PlantillaCertificado::paraTipo($tipo);
        $plantilla->update($datos);

        return $plantilla->fresh();
    }

    /**
     * PDF de muestra con datos ficticios, para que quien edita la plantilla
     * vea el resultado real (mismo pipeline de renderizado que un
     * certificado de verdad) sin tener que emitir uno.
     */
    public function previsualizarPlantilla(PlantillaCertificado $plantilla): string
    {
        $certificado = new Certificado([
            'tipo' => $plantilla->tipo,
            'numero' => '000000-'.now()->format('Y'),
            'numero_registro' => $plantilla->tipo->esCapacitacion() ? '0000000000' : null,
            'codigo_verificacion' => 'MUESTRAMUES',
            'es_duplicado' => false,
            'fecha_emision' => now(),
        ]);

        $certificado->setRelation('estudiante', new Estudiante([
            'nombres' => 'Estudiante',
            'apellidos' => 'De Ejemplo',
            'dni' => '00000000',
        ]));
        $certificado->setRelation('matricula', null);
        $certificado->setRelation('cursoCapacitacion', $plantilla->tipo->esCapacitacion()
            ? new CursoCapacitacion(['nombre' => 'Curso de ejemplo', 'horas_lectivas' => 100, 'documento_autorizacion' => 'R.D.R. N.°0000-0000-DREP'])
            : null);

        return $this->renderizarPdf($certificado, $plantilla)->output();
    }

    /**
     * El código puede ser el codigo_verificacion (alfanumérico, lo genera
     * el sistema) de un certificado/constancia académico, o el
     * numero_registro (numérico, lo escribe el staff a mano) de un
     * certificado de capacitación -- ambos son "el código impreso en el
     * documento" desde el punto de vista de quien lo escanea o lo tipea acá.
     */
    public function verificar(string $codigo): ?Certificado
    {
        $codigo = trim($codigo);

        return Certificado::query()
            ->where(function ($query) use ($codigo) {
                $query->where('codigo_verificacion', strtoupper($codigo))
                    ->orWhere('numero_registro', $codigo);
            })
            ->where('es_duplicado', false)
            ->with(['estudiante', 'matricula.grado', 'matricula.ciclo', 'cursoCapacitacion'])
            ->first();
    }

    /**
     * @return Collection<int, SolicitudCertificado>
     */
    public function solicitudesPendientes(): Collection
    {
        return SolicitudCertificado::query()
            ->where('estado', EstadoSolicitudCertificadoEnum::PENDIENTE)
            ->with(['estudiante', 'matricula', 'media'])
            ->oldest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, SolicitudCertificado>
     */
    public function misSolicitudes(Estudiante $estudiante): Collection
    {
        return SolicitudCertificado::query()
            ->where('estudiante_id', $estudiante->id)
            ->with(['certificado', 'media'])
            ->latest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Certificado>
     */
    public function misCertificados(Estudiante $estudiante): Collection
    {
        return Certificado::query()
            ->where('estudiante_id', $estudiante->id)
            ->with(['matricula.grado', 'matricula.ciclo'])
            ->latest('fecha_emision')
            ->get();
    }

    /**
     * @return Collection<int, Certificado>
     */
    public function todos(): Collection
    {
        return Certificado::query()
            ->with(['estudiante', 'matricula.grado', 'cursoCapacitacion', 'emisor', 'entregadoPor'])
            ->latest('fecha_emision')
            ->get();
    }

    private function generarPdf(Certificado $certificado): void
    {
        $certificado->load(['estudiante', 'matricula.grado', 'matricula.ciclo', 'cursoCapacitacion']);

        $pdf = $this->renderizarPdf($certificado);

        $certificado->addMediaFromString($pdf->output())
            ->usingFileName("certificado-{$certificado->numero}.pdf")
            ->toMediaCollection('pdf');
    }

    /**
     * @return \Barryvdh\DomPDF\PDF
     */
    private function renderizarPdf(Certificado $certificado, ?PlantillaCertificado $plantilla = null)
    {
        $plantilla ??= PlantillaCertificado::paraTipo($certificado->tipo);

        return Pdf::loadView('pdf.certificado', [
            'certificado' => $certificado,
            'plantilla' => $plantilla,
            'cuerpo' => $plantilla->renderizarCuerpo($this->variablesDePlantilla($certificado)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function variablesDePlantilla(Certificado $certificado): array
    {
        $detalleMatricula = $certificado->matricula
            ? sprintf(
                'cursó estudios en el grado %s durante el ciclo %s (%s al %s),',
                $certificado->matricula->grado->nombre,
                $certificado->matricula->ciclo->nombre,
                $certificado->matricula->ciclo->fecha_inicio->format('d/m/Y'),
                $certificado->matricula->ciclo->fecha_fin->format('d/m/Y'),
            )
            : 'se encuentra registrado(a) en esta institución,';

        $curso = $certificado->cursoCapacitacion;

        return [
            'estudiante' => $certificado->estudiante?->nombreCompleto() ?? '—',
            'dni' => $certificado->estudiante->dni ?? '—',
            'detalle_matricula' => $detalleMatricula,
            'grado' => $certificado->matricula?->grado->nombre ?? 'grado correspondiente',
            // Sin "periodo" al inicio (a diferencia de "grado"): las plantillas ya
            // escriben la palabra "periodo" antes de este placeholder (p. ej. "en el
            // presente periodo {{periodo}}"), así que repetirla acá duplicaría la
            // palabra en el texto final.
            'periodo' => $certificado->matricula?->ciclo->nombre ?? 'correspondiente',
            'curso' => $curso !== null ? $curso->nombre : 'curso correspondiente',
            'horas_lectivas' => $curso !== null ? (string) $curso->horas_lectivas : '',
            'numero' => $certificado->numero,
            'fecha_emision' => $certificado->fecha_emision->format('d/m/Y'),
            'codigo_verificacion' => $certificado->codigo_verificacion,
        ];
    }

    private function siguienteNumero(): string
    {
        $anio = now()->format('Y');
        $emitidosEsteAnio = Certificado::query()->where('numero', 'like', "%-{$anio}")->count();

        return sprintf('%06d-%s', $emitidosEsteAnio + 1, $anio);
    }

    private function generarCodigoVerificacion(): string
    {
        do {
            $codigo = Str::upper(Str::random(10));
        } while (Certificado::query()->where('codigo_verificacion', $codigo)->exists());

        return $codigo;
    }

    private function validarPendiente(SolicitudCertificado $solicitud): void
    {
        if ($solicitud->estado !== EstadoSolicitudCertificadoEnum::PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => 'Esta solicitud ya fue atendida y no puede modificarse.',
            ]);
        }
    }
}
