<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones_procedencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->unique()->constrained('estudiantes')->cascadeOnDelete();
            $table->string('nombre_colegio', 150);
            $table->string('ubicacion', 150)->nullable();
            $table->unsignedSmallInteger('anio_egreso')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituciones_procedencia');
    }
};
