<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_capacitacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->unsignedSmallInteger('horas_lectivas');
            $table->string('documento_autorizacion', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_capacitacion');
    }
};
