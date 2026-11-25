<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza el enum fijo TipoConceptoEnum (6 valores) por una tabla
 * gestionable desde UI. `slug` es el identificador estable que el código
 * usa para casos especiales (ConceptoPago::esMensualidad()/esOtro()) --
 * se siembra igual a los valores del enum para no perder continuidad, y
 * no se expone para editar desde el CRUD (el nombre sí es libre).
 */
return new class extends Migration
{
    private const TIPOS = [
        ['slug' => 'matricula', 'nombre' => 'Matrícula'],
        ['slug' => 'mensualidad', 'nombre' => 'Mensualidad'],
        ['slug' => 'certificado', 'nombre' => 'Certificado'],
        ['slug' => 'constancia', 'nombre' => 'Constancia'],
        ['slug' => 'penalidad', 'nombre' => 'Penalidad'],
        ['slug' => 'otro', 'nombre' => 'Otro'],
    ];

    public function up(): void
    {
        Schema::create('tipos_concepto_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 30)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $ahora = now();

        foreach (self::TIPOS as $tipo) {
            DB::table('tipos_concepto_pago')->insert([...$tipo, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_concepto_pago');
    }
};
