<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fila única (siempre id=1): el formato y el texto del certificado
        // son institucionales, no hay "varias plantillas" que elegir --
        // ver PlantillaCertificado::actual().
        Schema::create('plantilla_certificados', function (Blueprint $table) {
            $table->id();
            $table->string('institucion');
            $table->string('titulo');
            $table->text('cuerpo');
            $table->text('pie_nota')->nullable();
            $table->string('color_acento', 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_certificados');
    }
};
