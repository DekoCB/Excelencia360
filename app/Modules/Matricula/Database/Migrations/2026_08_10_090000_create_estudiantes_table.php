<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dni', 15)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->date('fecha_nacimiento');
            $table->boolean('es_menor_edad');
            $table->string('estado_civil', 20)->nullable();
            $table->string('direccion', 150)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('estado', 15)->default('activo');
            $table->foreignId('grado_actual_id')->nullable()->constrained('grados')->nullOnDelete();
            $table->unsignedTinyInteger('ciclos_completados')->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiantes');
    }
};
