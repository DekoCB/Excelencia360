<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ModalidadEstudioEnum::DISTANCIA pasó a llamarse VIRTUAL (mismo campo,
 * solo corrige el nombre) -- esto solo actualiza datos, la columna ya
 * era texto libre y no cambia de tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('matriculas')->where('modalidad_estudio', 'distancia')->update(['modalidad_estudio' => 'virtual']);
    }

    public function down(): void
    {
        DB::table('matriculas')->where('modalidad_estudio', 'virtual')->update(['modalidad_estudio' => 'distancia']);
    }
};
