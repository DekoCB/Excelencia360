<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_id')->nullable()->constrained('campanias_whatsapp')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->nullable()->constrained('estudiantes')->nullOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->string('telefono');
            $table->text('contenido');
            $table->string('tipo');
            $table->string('estado')->default('pendiente');
            $table->string('external_id')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('enviado_en')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
    }
};
