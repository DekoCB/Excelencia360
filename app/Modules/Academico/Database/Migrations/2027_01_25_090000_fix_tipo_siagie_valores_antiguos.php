<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige filas de siagies.tipo que hayan quedado con los códigos
 * antiguos de PeriodoSiagieEnum ('1'/'2') por un bug en la migración
 * 2027_01_24_090000: copiaba matriculas.periodo_siagie tal cual en vez
 * de traducirlo a los valores de TipoSiagieEnum ('primero'/'segundo').
 * Un entorno que corrió aquella migración ya con el fix no tiene nada
 * que corregir aquí (no encuentra filas con '1'/'2' y no hace nada).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('siagies')->where('tipo', '1')->update(['tipo' => 'primero']);
        DB::table('siagies')->where('tipo', '2')->update(['tipo' => 'segundo']);
    }

    public function down(): void
    {
        DB::table('siagies')->where('tipo', 'primero')->update(['tipo' => '1']);
        DB::table('siagies')->where('tipo', 'segundo')->update(['tipo' => '2']);
    }
};
