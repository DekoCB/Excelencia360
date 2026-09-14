<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_clases_grabadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas_curso_virtual')->cascadeOnDelete();
            $table->unsignedInteger('semana')->nullable();
            $table->string('tipo', 15);
            $table->string('titulo', 150);
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_clases_grabadas');
    }
};
