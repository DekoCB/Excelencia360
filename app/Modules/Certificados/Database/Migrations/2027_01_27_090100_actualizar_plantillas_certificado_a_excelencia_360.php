<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las plantillas de certificado ya creadas con los valores por defecto de
 * CEBA (nombre de institución y pie de página) pasan a los de Excelencia
 * 360. Solo se tocan las filas que siguen con el texto por defecto: si
 * alguien personalizó una plantilla desde el panel, se respeta.
 */
return new class extends Migration
{
    private const INSTITUCION_ANTERIOR = 'Centro de Educación Básica Alternativa — CEBA';

    private const PIE_ANTERIOR = 'Verifique la autenticidad de este documento en la sección "Verificar certificado" del portal CEBA.';

    public function up(): void
    {
        $nombre = (string) config('institucion.nombre');

        DB::table('plantilla_certificados')
            ->where('institucion', self::INSTITUCION_ANTERIOR)
            ->update(['institucion' => $nombre]);

        DB::table('plantilla_certificados')
            ->where('pie_nota', self::PIE_ANTERIOR)
            ->update(['pie_nota' => 'Verifique la autenticidad de este documento en la sección "Verificar certificado" del portal de '.$nombre.'.']);
    }

    public function down(): void
    {
        $nombre = (string) config('institucion.nombre');

        DB::table('plantilla_certificados')
            ->where('institucion', $nombre)
            ->update(['institucion' => self::INSTITUCION_ANTERIOR]);

        DB::table('plantilla_certificados')
            ->where('pie_nota', 'Verifique la autenticidad de este documento en la sección "Verificar certificado" del portal de '.$nombre.'.')
            ->update(['pie_nota' => self::PIE_ANTERIOR]);
    }
};
