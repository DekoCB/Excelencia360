<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Enums\MedioCuentaEnum;
use App\Modules\Pagos\Enums\TipoBilleteraEnum;
use App\Modules\Pagos\Models\CuentaBancaria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class CuentaBancariaService
{
    /**
     * @return Collection<int, CuentaBancaria>
     */
    public function listar(): Collection
    {
        return CuentaBancaria::query()->orderBy('medio')->orderBy('banco')->get();
    }

    /**
     * @return Collection<int, CuentaBancaria>
     */
    public function activas(): Collection
    {
        return CuentaBancaria::query()->where('activa', true)->orderBy('medio')->orderBy('banco')->get();
    }

    public function crear(
        MedioCuentaEnum $medio,
        ?string $banco,
        ?string $numeroCuenta,
        ?string $cci,
        ?TipoBilleteraEnum $tipoBilletera,
        ?string $celular,
        string $titular,
        ?UploadedFile $qr,
    ): CuentaBancaria {
        /** @var CuentaBancaria $cuenta */
        $cuenta = CuentaBancaria::query()->create([
            'medio' => $medio,
            'banco' => $banco,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'tipo_billetera' => $tipoBilletera,
            'celular' => $celular,
            'titular' => $titular,
            'activa' => true,
        ]);

        if ($qr) {
            $cuenta->addMedia($qr)->toMediaCollection('qr');
        }

        return $cuenta;
    }

    public function actualizar(
        CuentaBancaria $cuenta,
        MedioCuentaEnum $medio,
        ?string $banco,
        ?string $numeroCuenta,
        ?string $cci,
        ?TipoBilleteraEnum $tipoBilletera,
        ?string $celular,
        string $titular,
        bool $activa,
        ?UploadedFile $qr,
    ): CuentaBancaria {
        $cuenta->update([
            'medio' => $medio,
            'banco' => $banco,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'tipo_billetera' => $tipoBilletera,
            'celular' => $celular,
            'titular' => $titular,
            'activa' => $activa,
        ]);

        if ($qr) {
            $cuenta->addMedia($qr)->toMediaCollection('qr');
        }

        return $cuenta;
    }
}
