<?php

use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Registro de un estudiante nuevo (datos, apoderado si es menor,
 * documentos) -- sin matrícula: eso es lo único que hace el módulo de
 * Matrícula aparte (ver matricula/nueva/index.blade.php), una vez que
 * el estudiante ya está registrado. El examen de ubicación ya no se
 * pide acá (se sacó del wizard); sigue pudiendo registrarse después
 * desde la ficha del estudiante si hace falta.
 */
new class extends Component
{
    use WithFileUploads;

    public int $paso = 1;

    // Paso 1 — estudiante
    public string $nombres = '';

    public string $apellidos = '';

    public string $dni = '';

    public string $email = '';

    public string $fechaNacimiento = '';

    public string $estadoCivil = '';

    public string $direccion = '';

    public string $celular = '';

    /**
     * Celulares adicionales, solo para mayores de edad (ver
     * EstudianteTelefono) -- el menor de edad se comunica a través del
     * celular del apoderado, no tiene sentido pedirle más de uno propio.
     *
     * @var list<string>
     */
    public array $celularesAdicionales = [];

    public string $observacionesEstudiante = '';

    public $foto = null;

    // Paso 2 — apoderado (condicional)
    public string $apoderadoNombres = '';

    public string $apoderadoDni = '';

    public string $apoderadoCelular = '';

    public string $apoderadoCorreo = '';

    public string $apoderadoDireccion = '';

    public string $apoderadoParentesco = '';

    // Paso 3 — documentos e institución de procedencia
    public $dniEstudianteCaraArchivo = null;

    public $dniEstudianteReversoArchivo = null;

    public $dniApoderadoCaraArchivo = null;

    public $dniApoderadoReversoArchivo = null;

    public $certificadoArchivo = null;

    public $constanciaArchivo = null;

    public string $colegioNombre = '';

    public string $colegioUbicacion = '';

    public string $colegioAnioEgreso = '';

    public function mount(): void
    {
        Gate::authorize('matricula.crear');
    }

    #[Computed]
    public function esMenorDeEdad(): bool
    {
        if ($this->fechaNacimiento === '') {
            return false;
        }

        return MatriculaService::esMenorDeEdad($this->fechaNacimiento);
    }

    public function agregarCelular(): void
    {
        $this->celularesAdicionales[] = '';
    }

    public function quitarCelular(int $indice): void
    {
        unset($this->celularesAdicionales[$indice]);
        $this->celularesAdicionales = array_values($this->celularesAdicionales);
    }

    public function avanzar(MatriculaService $service): void
    {
        if ($this->paso === 1) {
            $this->validate([
                'nombres' => 'required|string|max:100',
                'apellidos' => 'required|string|max:100',
                'dni' => 'required|string|min:8|max:12',
                'email' => 'required|email|max:150',
                'fechaNacimiento' => 'nullable|date|before:today',
                'estadoCivil' => 'nullable|string|in:'.implode(',', array_column(EstadoCivilEnum::cases(), 'value')),
                'direccion' => 'nullable|string|max:150',
                'celular' => 'nullable|string',
                'celularesAdicionales.*' => 'nullable|string',
            ]);

            if (! $service->dniDisponible($this->dni)) {
                $this->addError('dni', 'Ya existe un estudiante registrado con este DNI. Puedes matricularlo desde el módulo de Matrícula.');

                return;
            }

            if (! $service->emailDisponible($this->email)) {
                $this->addError('email', 'Ya existe otro usuario con este correo.');

                return;
            }

            $this->paso = $this->esMenorDeEdad ? 2 : 3;

            return;
        }

        if ($this->paso === 2) {
            $this->validate([
                'apoderadoNombres' => 'required|string|max:150',
                'apoderadoDni' => 'required|string|min:8|max:12',
                'apoderadoCelular' => 'required|string',
                'apoderadoCorreo' => 'nullable|email|max:150',
                'apoderadoDireccion' => 'nullable|string|max:150',
                'apoderadoParentesco' => 'required|string|max:50',
            ]);

            $this->paso = 3;

            return;
        }
    }

    /**
     * El paso 3 (documentos e institución de procedencia) es el último
     * del wizard -- a diferencia de los pasos 1 y 2, no avanza a nada
     * más, así que su validación corre acá, justo antes de guardar, en
     * vez de en avanzar().
     */
    private function validarPaso3(): void
    {
        $this->validate([
            'dniEstudianteCaraArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'dniEstudianteReversoArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'dniApoderadoCaraArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'dniApoderadoReversoArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'certificadoArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'constanciaArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'colegioNombre' => 'nullable|string|max:150',
            'colegioUbicacion' => 'nullable|string|max:150',
            'colegioAnioEgreso' => 'nullable|integer|min:1950|max:'.now()->year,
        ]);
    }

    public function retroceder(): void
    {
        if ($this->paso === 3 && ! $this->esMenorDeEdad) {
            $this->paso = 1;

            return;
        }

        $this->paso = max(1, $this->paso - 1);
    }

    public function cancelar(): void
    {
        $this->dispatch('registrar-cerrado');
    }

    public function confirmar(
        MatriculaService $matriculaService,
        DocumentoEstudianteService $documentoService,
    ): void {
        Gate::authorize('matricula.crear');

        $this->validarPaso3();

        $estudiante = DB::transaction(function () use ($matriculaService, $documentoService) {
            $estudiante = $matriculaService->registrarEstudiante(new RegistrarEstudianteData(
                nombres: $this->nombres,
                apellidos: $this->apellidos,
                dni: new Dni($this->dni),
                email: $this->email,
                fechaNacimiento: $this->fechaNacimiento !== '' ? $this->fechaNacimiento : null,
                estadoCivil: $this->estadoCivil !== '' ? EstadoCivilEnum::from($this->estadoCivil) : null,
                direccion: $this->direccion ?: null,
                celular: $this->celular !== '' ? new Telefono($this->celular) : null,
                observaciones: $this->observacionesEstudiante ?: null,
            ));

            if ($this->foto) {
                $estudiante->addMedia($this->foto->getRealPath())
                    ->usingFileName('foto-'.$estudiante->id.'.'.$this->foto->getClientOriginalExtension())
                    ->preservingOriginal()
                    ->toMediaCollection('foto');
            }

            if ($this->esMenorDeEdad) {
                $matriculaService->registrarApoderado($estudiante, new RegistrarApoderadoData(
                    nombres: $this->apoderadoNombres,
                    dni: new Dni($this->apoderadoDni),
                    celular: new Telefono($this->apoderadoCelular),
                    correo: $this->apoderadoCorreo ?: null,
                    direccion: $this->apoderadoDireccion ?: null,
                    parentesco: $this->apoderadoParentesco,
                ));
            }

            if ($this->colegioNombre !== '') {
                $matriculaService->registrarInstitucionProcedencia($estudiante, [
                    'nombre_colegio' => $this->colegioNombre,
                    'ubicacion' => $this->colegioUbicacion ?: null,
                    'anio_egreso' => $this->colegioAnioEgreso !== '' ? (int) $this->colegioAnioEgreso : null,
                ]);
            }

            if ($this->dniEstudianteCaraArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::DNI_ESTUDIANTE, $this->dniEstudianteCaraArchivo, auth()->id(), $this->dniEstudianteReversoArchivo);
            }
            if ($this->dniApoderadoCaraArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::DNI_APODERADO, $this->dniApoderadoCaraArchivo, auth()->id(), $this->dniApoderadoReversoArchivo);
            }
            if ($this->certificadoArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, $this->certificadoArchivo, auth()->id());
            }
            if ($this->constanciaArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::CONSTANCIA, $this->constanciaArchivo, auth()->id());
            }

            foreach ($this->celularesAdicionales as $numero) {
                if ($numero !== '') {
                    $estudiante->telefonos()->create(['numero' => (string) new Telefono($numero)]);
                }
            }

            return $estudiante;
        });

        $this->dispatch('estudiante-registrado', estudianteId: $estudiante->id, nombre: $estudiante->nombreCompleto());
    }

    public function with(): array
    {
        return [
            'estadosCiviles' => EstadoCivilEnum::cases(),
        ];
    }
}; ?>

<div
    x-data="{ mostrar: false }"
    x-init="$nextTick(() => mostrar = true)"
    x-show="mostrar"
    x-cloak
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 px-4 py-8"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div
        x-show="mostrar"
        class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-border bg-surface-elevated p-6 shadow-lg"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl text-ink">Registrar estudiante</h1>
                <p class="mt-1 text-sm text-ink-dim">Paso {{ $paso }} de 3</p>
            </div>
            <button
                type="button"
                x-on:click="mostrar = false; setTimeout(() => $wire.cancelar(), 200)"
                class="rounded-md p-1 text-ink-faint hover:bg-surface-2 hover:text-ink"
                aria-label="Cerrar"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="mb-6 mt-4 flex gap-1">
            @for ($i = 1; $i <= 3; $i++)
                <div @class(['h-1.5 flex-1 rounded-full', 'bg-accent' => $i <= $paso, 'bg-surface-2' => $i > $paso])></div>
            @endfor
        </div>

        <div>
        {{-- Paso 1: Estudiante --}}
        @if ($paso === 1)
            <h2 class="font-display text-lg text-ink">Datos del estudiante</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="nombres" value="Nombres" />
                    <x-text-input wire:model="nombres" id="nombres" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('nombres')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apellidos" value="Apellidos" />
                    <x-text-input wire:model="apellidos" id="apellidos" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apellidos')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="dni" value="DNI" />
                    <x-text-input wire:model="dni" id="dni" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="email" value="Correo personal" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" placeholder="correo@ejemplo.com" />
                    <p class="mt-1 text-xs text-ink-faint">Con este correo ingresará al sistema (la contraseña inicial es su DNI).</p>
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="fechaNacimiento" value="Fecha de nacimiento (opcional)" />
                    <x-date-input wire:model.live="fechaNacimiento" id="fechaNacimiento" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaNacimiento')" class="mt-1" />
                    @if ($fechaNacimiento)
                        <p class="mt-1 text-xs text-ink-faint">{{ $this->esMenorDeEdad ? 'Menor de edad — se pedirán datos del apoderado.' : 'Mayor de edad.' }}</p>
                    @endif
                </div>
                <div>
                    <x-input-label for="estadoCivil" value="Estado civil (opcional)" />
                    <x-select-input
                        wire:model="estadoCivil"
                        id="estadoCivil"
                        class="mt-1 block w-full"
                        :options="collect($estadosCiviles)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->label()])->prepend('Sin especificar', '')"
                    />
                </div>
                <div>
                    <x-input-label for="celular" value="Celular" />
                    <x-text-input wire:model="celular" id="celular" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('celular')" class="mt-1" />
                </div>
                @unless ($this->esMenorDeEdad)
                    <div class="sm:col-span-2">
                        <x-input-label value="Celulares adicionales (opcional)" />
                        <div class="mt-1 space-y-2">
                            @foreach ($celularesAdicionales as $indice => $numero)
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model="celularesAdicionales.{{ $indice }}" class="block w-full" />
                                    <button type="button" wire:click="quitarCelular({{ $indice }})" class="text-sm text-danger hover:underline">Quitar</button>
                                </div>
                                <x-input-error :messages="$errors->get('celularesAdicionales.'.$indice)" />
                            @endforeach
                        </div>
                        <button type="button" wire:click="agregarCelular" class="mt-2 text-sm font-medium text-accent hover:underline">+ Agregar otro celular</button>
                    </div>
                @endunless
                <div class="sm:col-span-2">
                    <x-input-label for="direccion" value="Dirección" />
                    <x-text-input wire:model="direccion" id="direccion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('direccion')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="foto" value="Fotografía (opcional)" />
                    <input wire:model="foto" id="foto" type="file" accept="image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('foto')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="observacionesEstudiante" value="Observaciones (opcional)" />
                    <p class="mt-1 text-xs text-ink-faint">Acuerdos especiales, documentos pendientes de entregar, casos particulares (ej. examen de ubicación).</p>
                    <textarea wire:model="observacionesEstudiante" id="observacionesEstudiante" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    <x-input-error :messages="$errors->get('observacionesEstudiante')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 2: Apoderado --}}
        @if ($paso === 2)
            <h2 class="font-display text-lg text-ink">Datos del apoderado</h2>
            <p class="mt-1 text-sm text-ink-dim">El estudiante es menor de edad, así que necesitamos los datos de su apoderado.</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="apoderadoNombres" value="Nombres completos" />
                    <x-text-input wire:model="apoderadoNombres" id="apoderadoNombres" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoNombres')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoDni" value="DNI" />
                    <x-text-input wire:model="apoderadoDni" id="apoderadoDni" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoDni')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoParentesco" value="Parentesco" />
                    <x-text-input wire:model="apoderadoParentesco" id="apoderadoParentesco" class="mt-1 block w-full" placeholder="Madre, padre, tío…" />
                    <x-input-error :messages="$errors->get('apoderadoParentesco')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoCelular" value="Celular" />
                    <x-text-input wire:model="apoderadoCelular" id="apoderadoCelular" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoCelular')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoCorreo" value="Correo (opcional)" />
                    <x-text-input wire:model="apoderadoCorreo" id="apoderadoCorreo" type="email" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoCorreo')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="apoderadoDireccion" value="Dirección (opcional)" />
                    <x-text-input wire:model="apoderadoDireccion" id="apoderadoDireccion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoDireccion')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 3: Documentos e institución de procedencia --}}
        @if ($paso === 3)
            <h2 class="font-display text-lg text-ink">Documentos e institución de procedencia</h2>
            <p class="mt-1 text-sm text-ink-faint">Todos los documentos de este paso son opcionales: si no los tienes ahora, puedes subirlos o editarlos después desde la ficha del estudiante.</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="dniEstudianteCaraArchivo" value="DNI del estudiante — cara (opcional)" />
                    <input wire:model="dniEstudianteCaraArchivo" id="dniEstudianteCaraArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('dniEstudianteCaraArchivo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="dniEstudianteReversoArchivo" value="DNI del estudiante — sello/reverso (opcional)" />
                    <input wire:model="dniEstudianteReversoArchivo" id="dniEstudianteReversoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('dniEstudianteReversoArchivo')" class="mt-1" />
                </div>
                @if ($this->esMenorDeEdad)
                    <div>
                        <x-input-label for="dniApoderadoCaraArchivo" value="DNI del apoderado — cara (opcional)" />
                        <input wire:model="dniApoderadoCaraArchivo" id="dniApoderadoCaraArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('dniApoderadoCaraArchivo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="dniApoderadoReversoArchivo" value="DNI del apoderado — sello/reverso (opcional)" />
                        <input wire:model="dniApoderadoReversoArchivo" id="dniApoderadoReversoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('dniApoderadoReversoArchivo')" class="mt-1" />
                    </div>
                @endif
                <div>
                    <x-input-label for="certificadoArchivo" value="Certificado de estudios (opcional)" />
                    <input wire:model="certificadoArchivo" id="certificadoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('certificadoArchivo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="constanciaArchivo" value="Constancia (opcional)" />
                    <input wire:model="constanciaArchivo" id="constanciaArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('constanciaArchivo')" class="mt-1" />
                </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-ink">Institución de procedencia (opcional)</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="colegioNombre" value="Colegio anterior" />
                    <x-text-input wire:model="colegioNombre" id="colegioNombre" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioNombre')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="colegioUbicacion" value="Ubicación" />
                    <x-text-input wire:model="colegioUbicacion" id="colegioUbicacion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioUbicacion')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="colegioAnioEgreso" value="Año de egreso" />
                    <x-text-input wire:model="colegioAnioEgreso" id="colegioAnioEgreso" type="number" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioAnioEgreso')" class="mt-1" />
                </div>
            </div>
        @endif

        <div class="mt-6 flex justify-between border-t border-border pt-4">
            @if ($paso > 1)
                <x-secondary-button type="button" wire:click="retroceder">Atrás</x-secondary-button>
            @else
                <span></span>
            @endif

            @if ($paso < 3)
                <x-primary-button type="button" wire:click="avanzar">Continuar</x-primary-button>
            @else
                <x-primary-button type="button" wire:click="confirmar">Registrar estudiante</x-primary-button>
            @endif
        </div>
        </div>
    </div>
</div>
