<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table) {
            $table->id();
            // A diferencia de Docente, esta tabla no extiende a un User: es
            // personal que no inicia sesión en el sistema (portería,
            // limpieza, psicología, etc.), así que guarda su propia
            // identidad en vez de depender de una cuenta.
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('dni', 12)->unique();
            $table->string('celular')->nullable();
            $table->string('cargo');
            $table->string('area')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal');
    }
};
