<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('siagies');
    }

    public function down(): void
    {
        Schema::create('siagies', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);
            $table->integer('anio');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 15)->default('planificado');
            $table->timestamps();
            $table->unique(['tipo', 'anio']);
        });
    }
};
