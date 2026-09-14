<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanias_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('plantilla_id')->constrained('plantillas_whatsapp')->cascadeOnDelete();
            $table->json('segmento');
            $table->string('estado')->default('borrador');
            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('enviados')->default(0);
            $table->unsignedInteger('fallidos')->default(0);
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanias_whatsapp');
    }
};
