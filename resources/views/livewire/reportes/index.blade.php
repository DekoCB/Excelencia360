<?php

use App\Models\User;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Siagie;
use App\Modules\Reportes\Exports\ReporteExport;
use App\Modules\Reportes\Services\ReporteService;
use App\Shared\Enums\RolEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * @var array<string, array{permiso: string, label: string}>
     */
    private const TIPOS = [
        'matricula' => ['permiso' => 'reportes.matricula', 'label' => 'Matrícula'],
        'academico' => ['permiso' => 'reportes.academicos', 'label' => 'Académico'],
        'financiero' => ['permiso' => 'reportes.financieros', 'label' => 'Financiero'],
        'morosos' => ['permiso' => 'reportes.morosos', 'label' => 'Deudores'],
        'certificados' => ['permiso' => 'reportes.certificados', 'label' => 'Certificados'],
        'operativo' => ['permiso' => 'reportes.operativos', 'label' => 'Operativo (asistencia)'],
        'propio' => ['permiso' => 'reportes.propios', 'label' => 'Mis evaluaciones'],
    ];

    /**
     * Tipos de reporte cuyos datos se originan en un Horario (clase
     * recurrente: curso + docente + día + hora) y por lo tanto admiten
     * filtrarse por franja institucional, además de Grupo/Grado/Curso.
     *
     * @var list<string>
     */
    private const TIPOS_CON_HORARIO = ['matricula', 'academico', 'financiero', 'morosos', 'certificados', 'operativo', 'propio'];

    public string $tipo = '';

    public string $siagieId = '';

    public string $cicloId = '';

    public string $gradoId = '';

    public string $cursoId = '';

    public string $franja = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasAnyPermission(array_column(self::TIPOS, 'permiso')), 403);

        $this->tipo = collect(self::TIPOS)
            ->keys()
            ->first(fn (string $tipo) => $this->tipoDisponible($tipo, $user)) ?? '';
    }

    /**
     * "Mis evaluaciones" es un reporte pensado para que un docente vea sus
     * propios horarios -- los roles superiores (Dirección, Coordinador,
     * etc.) técnicamente pasan el permiso `reportes.propios` porque
     * Dirección tiene el comodín '*', pero no dictan horarios propios, así
     * que no tiene sentido ofrecérselo a nadie que no sea Docente.
     */
    private function tipoDisponible(string $tipo, User $user): bool
    {
        if (! $user->hasPermissionTo(self::TIPOS[$tipo]['permiso'])) {
            return false;
        }

        if ($tipo === 'propio' && ! $user->hasRole(RolEnum::DOCENTE->value)) {
            return false;
        }

        return true;
    }

    public function updatingTipo(): void
    {
        $this->franja = '';
    }

    /**
     * El filtro es en cascada: SIAGIE primero, luego Grupo, luego Grado,
     * luego Curso. Cambiar un nivel invalida los que dependen de él.
     */
    public function updatedSiagieId(): void
    {
        $this->cicloId = '';
        $this->gradoId = '';
        $this->cursoId = '';
    }

    public function updatedCicloId(): void
    {
        $this->gradoId = '';
        $this->cursoId = '';
    }

    public function updatedGradoId(): void
    {
        $this->cursoId = '';
    }

    public function exportarExcel(ReporteService $reportes)
    {
        return $this->exportar($reportes, 'excel');
    }

    public function exportarCsv(ReporteService $reportes)
    {
        return $this->exportar($reportes, 'csv');
    }

    public function exportarPdf(ReporteService $reportes)
    {
        return $this->exportar($reportes, 'pdf');
    }

    private function exportar(ReporteService $reportes, string $formato)
    {
        abort_unless(Auth::user()->hasPermissionTo('reportes.exportar'), 403);

        ['columnas' => $columnas, 'filas' => $filas] = $this->generarReporte($reportes);
        $nombreArchivo = "reporte-{$this->tipo}-".now()->format('Y-m-d');

        return match ($formato) {
            'excel' => Excel::download(new ReporteExport($columnas, $filas), "{$nombreArchivo}.xlsx"),
            'csv' => Excel::download(new ReporteExport($columnas, $filas), "{$nombreArchivo}.csv", ExcelFormat::CSV),
            // No se puede usar Pdf::loadView(...)->download() aquí: eso devuelve un
            // Illuminate\Http\Response plano, que Livewire no reconoce como descarga
            // de archivo (solo detecta StreamedResponse/BinaryFileResponse). Sin ese
            // reconocimiento, intenta serializar los bytes binarios del PDF como si
            // fueran el valor de retorno normal de la acción, y el json_encode()
            // interno truena con "Malformed UTF-8 characters". streamDownload()
            // devuelve un StreamedResponse, que sí reconoce.
            'pdf' => response()->streamDownload(
                fn () => print (Pdf::loadView('pdf.reporte', [
                    'titulo' => self::TIPOS[$this->tipo]['label'] ?? 'Reporte',
                    'columnas' => $columnas,
                    'filas' => $filas,
                ])->output()),
                "{$nombreArchivo}.pdf",
                ['Content-Type' => 'application/pdf'],
            ),
        };
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    private function generarReporte(ReporteService $reportes): array
    {
        $siagieId = $this->siagieId !== '' ? (int) $this->siagieId : null;
        $cicloId = $this->cicloId !== '' ? (int) $this->cicloId : null;
        $gradoId = $this->gradoId !== '' ? (int) $this->gradoId : null;
        $cursoId = $this->cursoId !== '' ? (int) $this->cursoId : null;
        $franja = $this->franja !== '' ? $this->franja : null;

        return match ($this->tipo) {
            'matricula' => $reportes->matricula($cicloId, $gradoId, $cursoId, $franja, $siagieId),
            'academico' => $reportes->academico($cicloId, $gradoId, $cursoId, $franja, $siagieId),
            'financiero' => $reportes->financiero($cicloId, $gradoId, $cursoId, $franja, $siagieId),
            'morosos' => $reportes->morosos($cicloId, $gradoId, $cursoId, $franja, $siagieId),
            'certificados' => $reportes->certificados($cicloId, $gradoId, $cursoId, $franja, $siagieId),
            'operativo' => $reportes->operativo($cicloId, $gradoId, $cursoId, $franja, $siagieId),
            'propio' => $reportes->propio(Auth::user(), $cicloId, $gradoId, $cursoId, $franja, $siagieId),
            default => ['columnas' => [], 'filas' => []],
        };
    }

    /**
     * @return Collection<int, Grado>
     */
    private function gradosDisponibles(): Collection
    {
        return Grado::query()->where('activo', true)->orderBy('orden')->get();
    }

    /**
     * @return Collection<int, Curso>
     */
    private function cursosDisponibles(): Collection
    {
        if ($this->gradoId === '') {
            return collect();
        }

        return Curso::query()
            ->where('grado_id', (int) $this->gradoId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Las 3 franjas institucionales fijas (ver FranjaHorarioEnum): un curso
     * puede dictarse en cualquiera de ellas, así que filtrar por franja
     * -- no por un horario puntual -- trae todos los cursos que caen ahí,
     * sin importar grado ni docente.
     *
     * @return Collection<int, array{value: string, label: string}>
     */
    private function franjasDisponibles(): Collection
    {
        if (! in_array($this->tipo, self::TIPOS_CON_HORARIO, true)) {
            return collect();
        }

        return collect(FranjaHorarioEnum::cases())
            ->map(fn (FranjaHorarioEnum $franja) => ['value' => $franja->value, 'label' => $franja->label()]);
    }

    public function with(ReporteService $reportes): array
    {
        $user = Auth::user();

        $tiposDisponibles = collect(self::TIPOS)
            ->filter(fn (array $config, string $tipo) => $this->tipoDisponible($tipo, $user))
            ->map(fn (array $config, string $tipo) => ['value' => $tipo, 'label' => $config['label']])
            ->values();

        $reporte = $this->tipo !== '' && $this->tipoDisponible($this->tipo, $user)
            ? $this->generarReporte($reportes)
            : ['columnas' => [], 'filas' => []];

        return [
            'tiposDisponibles' => $tiposDisponibles,
            'reporte' => $reporte,
            'puedeExportar' => $user->hasPermissionTo('reportes.exportar'),
            'franjasDisponibles' => $this->franjasDisponibles(),
            'siagiesDisponibles' => Siagie::query()->orderByDesc('anio')->orderBy('tipo')->get(),
            'ciclosDisponibles' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'gradosDisponibles' => $this->gradosDisponibles(),
            'cursosDisponibles' => $this->cursosDisponibles(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Reportes</h1>
        <p class="mt-1 text-sm text-ink-dim">Construye un reporte, revisa la vista previa y expórtalo a Excel, CSV o PDF.</p>
    </x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-4 rounded-2xl border border-border bg-surface shadow-sm p-4">
            <div>
                <x-input-label for="tipo" value="Tipo de reporte" />
                <x-select-input
                    wire:model.live="tipo"
                    id="tipo"
                    class="mt-1 block w-56"
                    :options="collect($tiposDisponibles)->mapWithKeys(fn ($opcion) => [$opcion['value'] => $opcion['label']])"
                />
            </div>
            <div>
                <x-input-label for="siagieId" value="SIAGIE" />
                <x-select-input
                    wire:model.live="siagieId"
                    id="siagieId"
                    class="mt-1 block w-48"
                    :options="collect($siagiesDisponibles)->mapWithKeys(fn ($siagie) => [$siagie->id => $siagie->nombreCompleto()])->prepend('Todos los SIAGIE', '')"
                />
            </div>
            {{--
                wire:key fuerza a Livewire a destruir y recrear este bloque
                cuando cambia de qué depende su lista de opciones -- si no,
                el x-data de x-select-input (que solo se evalúa una vez, al
                crearse el nodo) queda con las opciones "congeladas" del
                primer render y nunca ve las nuevas tras un morph.
            --}}
            <div wire:key="ciclo-select-{{ $siagieId }}">
                <x-input-label for="cicloId" value="Grupo" />
                <x-select-input
                    wire:model.live="cicloId"
                    id="cicloId"
                    class="mt-1 block w-56"
                    :disabled="$siagieId === ''"
                    :options="collect($ciclosDisponibles)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])->prepend('Todos los grupos', '')"
                />
            </div>
            <div wire:key="grado-select-{{ $cicloId }}">
                <x-input-label for="gradoId" value="Grado" />
                <x-select-input
                    wire:model.live="gradoId"
                    id="gradoId"
                    class="mt-1 block w-48"
                    :disabled="$cicloId === ''"
                    :options="collect($gradosDisponibles)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])->prepend('Todos los grados', '')"
                />
            </div>
            <div wire:key="curso-select-{{ $gradoId }}">
                <x-input-label for="cursoId" value="Curso" />
                <x-select-input
                    wire:model.live="cursoId"
                    id="cursoId"
                    class="mt-1 block w-48"
                    :disabled="$gradoId === ''"
                    :options="collect($cursosDisponibles)->mapWithKeys(fn ($curso) => [$curso->id => $curso->nombre])->prepend('Todos los cursos', '')"
                />
            </div>

            @if ($franjasDisponibles->isNotEmpty())
                <div>
                    <x-input-label for="franja" value="Horario (opcional)" />
                    <x-select-input
                        wire:model.live="franja"
                        id="franja"
                        class="mt-1 block w-64"
                        :options="collect($franjasDisponibles)->mapWithKeys(fn ($opcion) => [$opcion['value'] => $opcion['label']])->prepend('Todos los horarios', '')"
                    />
                </div>
            @endif

            @if ($puedeExportar)
                <div class="ml-auto flex gap-2">
                    <x-secondary-button type="button" wire:click="exportarExcel">Excel</x-secondary-button>
                    <x-secondary-button type="button" wire:click="exportarCsv">CSV</x-secondary-button>
                    <x-secondary-button type="button" wire:click="exportarPdf">PDF</x-secondary-button>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto rounded-2xl border border-border bg-surface shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        @foreach ($reporte['columnas'] as $columna)
                            <th class="whitespace-nowrap px-4 py-2 font-mono text-xs uppercase tracking-wide text-ink-faint">{{ $columna }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($reporte['filas'] as $fila)
                        <tr>
                            @foreach ($fila as $valor)
                                <td class="whitespace-nowrap px-4 py-2 text-ink">{{ $valor }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(count($reporte['columnas']), 1) }}" class="px-4 py-8 text-center text-sm text-ink-faint">
                                No hay datos para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
