<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * varchar(30) se queda corto para los nuevos valores del enum
 * ('constancia_practicas_preprofesionales', 37 caracteres) -- se amplía a
 * 50 en las tres tablas que guardan TipoDocumentoEnum como string. SQL
 * crudo (MODIFY) en vez del ->change() de Blueprint porque este proyecto
 * no tiene instalado doctrine/dbal, que ese método requiere.
 *
 * Solo corre en MySQL: SQLite (usado en los tests) no impone el límite de
 * longitud de un VARCHAR, así que ahí no hay nada que ampliar, y su
 * sintaxis ALTER no soporta MODIFY de todos modos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE certificados MODIFY tipo VARCHAR(50) NOT NULL DEFAULT 'certificado_estudios'");
        DB::statement("ALTER TABLE solicitudes_certificado MODIFY tipo VARCHAR(50) NOT NULL DEFAULT 'certificado_estudios'");
        DB::statement("ALTER TABLE plantilla_certificados MODIFY tipo VARCHAR(50) NOT NULL DEFAULT 'certificado_estudios'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE certificados MODIFY tipo VARCHAR(30) NOT NULL DEFAULT 'certificado_estudios'");
        DB::statement("ALTER TABLE solicitudes_certificado MODIFY tipo VARCHAR(30) NOT NULL DEFAULT 'certificado_estudios'");
        DB::statement("ALTER TABLE plantilla_certificados MODIFY tipo VARCHAR(30) NOT NULL DEFAULT 'certificado_estudios'");
    }
};
