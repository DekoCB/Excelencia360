<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_virtual_id')->constrained('aula_virtual_cursos')->cascadeOnDelete();
            $table->foreignId('autor_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foros');
    }
};
