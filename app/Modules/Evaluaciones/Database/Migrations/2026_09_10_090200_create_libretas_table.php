<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libretas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('ciclo_id')->constrained('ciclos')->cascadeOnDelete();
            $table->timestamp('generado_en')->nullable();
            $table->timestamps();

            $table->unique(['estudiante_id', 'ciclo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('libretas');
    }
};
