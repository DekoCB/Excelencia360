<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clases_grabadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_virtual_id')->constrained('aula_virtual_cursos')->cascadeOnDelete();
            $table->string('tipo', 15);
            $table->string('titulo', 150);
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clases_grabadas');
    }
};
