<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apoderados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->unique()->constrained('estudiantes')->cascadeOnDelete();
            $table->string('nombres', 150);
            $table->string('dni', 15);
            $table->string('celular', 20);
            $table->string('correo', 150)->nullable();
            $table->string('direccion', 150)->nullable();
            $table->string('parentesco', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apoderados');
    }
};
