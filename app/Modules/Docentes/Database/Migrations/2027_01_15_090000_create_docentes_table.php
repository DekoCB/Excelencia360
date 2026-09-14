<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            // Un docente siempre necesita entrar al sistema (asistencia,
            // aula virtual, evaluaciones) -- a diferencia de Estudiante,
            // donde user_id es opcional, acá es obligatorio y único: esta
            // tabla es un perfil que EXTIENDE a un User con rol docente,
            // no una identidad aparte. Nombre, DNI, teléfono y estado
            // siguen viviendo en users -- no se duplican acá.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('especialidad')->nullable();
            $table->string('grado_academico')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docentes');
    }
};
