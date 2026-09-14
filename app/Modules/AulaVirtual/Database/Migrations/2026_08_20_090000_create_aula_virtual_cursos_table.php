<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aula_virtual_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_id')->unique()->constrained('horarios')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aula_virtual_cursos');
    }
};
