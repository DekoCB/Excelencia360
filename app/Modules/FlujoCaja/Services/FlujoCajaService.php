<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Services;

use App\Models\User;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\FlujoCaja\Models\Egreso;
use App\Modules\FlujoCaja\Models\TurnoCaja;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\Pago;
use App\Shared\Enums\TurnoDiaEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

/**
 * Ingresos = Pago aprobado (mismo criterio que ya usa el Dashboard, ver
 * dashboard/index.blade.php: ingresosDelMes/calcularIngresosPorSemana,
 * agregando por fecha_aprobacion). Egresos son registrados a mano acá.
 */
class FlujoCajaService
{
    /**
     * @param  array{categoria: CategoriaEgresoEnum, descripcion: ?string, monto: float, metodo: MetodoPagoEnum, fecha_egreso: string}  $datos
     */
    public function registrarEgreso(array $datos, ?UploadedFile $comprobante, ?int $registradoPor): Egreso
    {
        $turnoAbierto = $registradoPor !== null ? $this->turnoAbiertoDeUsuarioId($registradoPor) : null;

        /** @var Egreso $egreso */
        $egreso = Egreso::query()->create([
            ...$datos,
            'registrado_por' => $registradoPor,
            'turno_caja_id' => $turnoAbierto?->id,
        ]);

        if ($comprobante) {
            $egreso->addMedia($comprobante)->toMediaCollection('comprobante');
        }

        return $egreso;
    }

    public function ingresosDelPeriodo(Carbon $inicio, Carbon $fin, ?int $usuarioId = null): float
    {
        return (float) Pago::query()
            ->where('estado', EstadoPagoEnum::APROBADO)
            ->whereBetween('fecha_aprobacion', [$inicio, $fin])
            ->when($usuarioId !== null, fn ($query) => $query->where('registrado_por', $usuarioId))
            ->sum('monto');
    }

    public function egresosDelPeriodo(Carbon $inicio, Carbon $fin, ?int $usuarioId = null): float
    {
        return (float) Egreso::query()
            ->whereBetween('fecha_egreso', [$inicio, $fin])
            ->when($usuarioId !== null, fn ($query) => $query->where('registrado_por', $usuarioId))
            ->sum('monto');
    }

    /**
     * El turno abierto de un usuario (si tiene uno) -- cada Pago registrado
     * y cada Egreso anotado mientras esté abierto se le vincula
     * automáticamente (ver registrarEgreso() y PagoService::registrar()).
     */
    public function turnoAbiertoDe(User $usuario): ?TurnoCaja
    {
        return $this->turnoAbiertoDeUsuarioId($usuario->id);
    }

    private function turnoAbiertoDeUsuarioId(int $usuarioId): ?TurnoCaja
    {
        return TurnoCaja::query()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'abierto')
            ->first();
    }

    public function abrirTurno(User $usuario, float $montoInicial, TurnoDiaEnum $turno): TurnoCaja
    {
        if ($this->turnoAbiertoDe($usuario) !== null) {
            throw ValidationException::withMessages([
                'montoInicial' => 'Ya tienes un turno de caja abierto.',
            ]);
        }

        return TurnoCaja::query()->create([
            'usuario_id' => $usuario->id,
            'turno' => $turno,
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'monto_inicial' => $montoInicial,
            'estado' => 'abierto',
        ]);
    }

    public function cerrarTurno(TurnoCaja $turno, float $montoFinalDeclarado): TurnoCaja
    {
        $turno->update([
            'hora_cierre' => now(),
            'monto_final_declarado' => $montoFinalDeclarado,
            'estado' => 'cerrado',
        ]);

        return $turno->refresh();
    }

    public function montoCalculadoDelTurno(TurnoCaja $turno): float
    {
        $ingresos = (float) Pago::query()
            ->where('estado', EstadoPagoEnum::APROBADO)
            ->where('turno_caja_id', $turno->id)
            ->sum('monto');

        $egresos = (float) Egreso::query()
            ->where('turno_caja_id', $turno->id)
            ->sum('monto');

        return (float) $turno->monto_inicial + $ingresos - $egresos;
    }

    /**
     * Mismo criterio y misma forma de retorno que movimientosDelPeriodo(),
     * filtrado por turno en vez de por rango de fechas.
     */
    public function movimientosDelTurno(TurnoCaja $turno): SupportCollection
    {
        $ingresos = Pago::query()
            ->where('estado', EstadoPagoEnum::APROBADO)
            ->where('turno_caja_id', $turno->id)
            ->with(['concepto', 'cargoAdicional', 'recibo'])
            ->get()
            ->map(fn (Pago $pago) => [
                'tipo' => 'ingreso',
                'fecha' => $pago->fecha_aprobacion ?? $pago->fecha_pago,
                'concepto' => $pago->nombreConcepto().($pago->detalle ? " — {$pago->detalle}" : ''),
                'metodo' => $pago->metodo->label(),
                'monto' => (float) $pago->monto,
                'comprobanteUrl' => $pago->recibo?->getFirstMediaUrl('pdf') ?: null,
            ]);

        $egresos = Egreso::query()
            ->where('turno_caja_id', $turno->id)
            ->get()
            ->map(fn (Egreso $egreso) => [
                'tipo' => 'egreso',
                'fecha' => $egreso->fecha_egreso,
                'concepto' => $egreso->categoria->label().($egreso->descripcion ? " — {$egreso->descripcion}" : ''),
                'metodo' => $egreso->metodo->label(),
                'monto' => (float) $egreso->monto,
                'comprobanteUrl' => $egreso->getFirstMediaUrl('comprobante') ?: null,
            ]);

        return $ingresos->concat($egresos)->sortByDesc('fecha')->values();
    }

    /**
     * El libro de movimientos del periodo: ingresos (Pago aprobado) y
     * egresos normalizados a la misma forma y mezclados por fecha
     * descendente. Sin generics en el tipo de retorno: el TValue de
     * Collection no es covariante (ver LibretaService::resumenPorCursos()
     * para el mismo caso), así que ninguna anotación de forma de array
     * sobrevive a un ->map()+->concat(). Cada elemento es un array con las
     * claves "tipo" ('ingreso'|'egreso'), "fecha" (Carbon), "concepto"
     * (string), "metodo" (string), "monto" (float) y "comprobanteUrl"
     * (?string).
     */
    public function movimientosDelPeriodo(Carbon $inicio, Carbon $fin, ?int $usuarioId = null): SupportCollection
    {
        $ingresos = Pago::query()
            ->where('estado', EstadoPagoEnum::APROBADO)
            ->whereBetween('fecha_aprobacion', [$inicio, $fin])
            ->when($usuarioId !== null, fn ($query) => $query->where('registrado_por', $usuarioId))
            ->with(['concepto', 'cargoAdicional', 'recibo'])
            ->get()
            ->map(fn (Pago $pago) => [
                'tipo' => 'ingreso',
                'fecha' => $pago->fecha_aprobacion ?? $pago->fecha_pago,
                'concepto' => $pago->nombreConcepto().($pago->detalle ? " — {$pago->detalle}" : ''),
                'metodo' => $pago->metodo->label(),
                'monto' => (float) $pago->monto,
                'comprobanteUrl' => $pago->recibo?->getFirstMediaUrl('pdf') ?: null,
            ]);

        $egresos = Egreso::query()
            ->whereBetween('fecha_egreso', [$inicio, $fin])
            ->when($usuarioId !== null, fn ($query) => $query->where('registrado_por', $usuarioId))
            ->get()
            ->map(fn (Egreso $egreso) => [
                'tipo' => 'egreso',
                'fecha' => $egreso->fecha_egreso,
                'concepto' => $egreso->categoria->label().($egreso->descripcion ? " — {$egreso->descripcion}" : ''),
                'metodo' => $egreso->metodo->label(),
                'monto' => (float) $egreso->monto,
                'comprobanteUrl' => $egreso->getFirstMediaUrl('comprobante') ?: null,
            ]);

        return $ingresos->concat($egresos)->sortByDesc('fecha')->values();
    }

    /**
     * Turnos cerrados, más reciente primero -- para el reporte de
     * apertura/cierre: cada fila ya trae listo lo necesario (monto
     * inicial, declarado, calculado y la diferencia) sin que la vista
     * tenga que recalcular nada.
     *
     * @return Collection<int, TurnoCaja>
     */
    public function turnosCerrados(?int $usuarioId = null): Collection
    {
        return TurnoCaja::query()
            ->where('estado', 'cerrado')
            ->when($usuarioId !== null, fn ($query) => $query->where('usuario_id', $usuarioId))
            ->with('usuario')
            ->latest('hora_cierre')
            ->get();
    }

    /**
     * Los usuarios que han registrado al menos un Pago o Egreso -- opciones
     * del filtro "por usuario" de la pantalla de Flujo de Caja.
     *
     * @return SupportCollection<int, User>
     */
    public function usuariosConMovimientos(): SupportCollection
    {
        $idsPagos = Pago::query()->whereNotNull('registrado_por')->distinct()->pluck('registrado_por');
        $idsEgresos = Egreso::query()->whereNotNull('registrado_por')->distinct()->pluck('registrado_por');

        return User::query()
            ->whereIn('id', $idsPagos->concat($idsEgresos)->unique())
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{0: list<string>, 1: list<float>}
     */
    public function ingresosPorMes(int $meses = 6): array
    {
        return $this->serieMensual($meses, fn (Carbon $inicio, Carbon $fin) => $this->ingresosDelPeriodo($inicio, $fin));
    }

    /**
     * @return array{0: list<string>, 1: list<float>}
     */
    public function egresosPorMes(int $meses = 6): array
    {
        return $this->serieMensual($meses, fn (Carbon $inicio, Carbon $fin) => $this->egresosDelPeriodo($inicio, $fin));
    }

    /**
     * @return array{0: list<string>, 1: list<float>}
     */
    private function serieMensual(int $meses, \Closure $montoDelPeriodo): array
    {
        $labels = [];
        $datos = [];

        for ($mesesAtras = $meses - 1; $mesesAtras >= 0; $mesesAtras--) {
            $inicio = now()->subMonths($mesesAtras)->startOfMonth();
            $fin = now()->subMonths($mesesAtras)->endOfMonth();

            $labels[] = ucfirst($inicio->translatedFormat('M'));
            $datos[] = $montoDelPeriodo($inicio, $fin);
        }

        return [$labels, $datos];
    }
}
