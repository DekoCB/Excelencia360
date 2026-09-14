<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_contacto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('email', 150);
            $table->string('telefono', 30);
            $table->string('programa_interes', 100)->nullable();
            $table->text('mensaje');
            $table->boolean('atendido')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_contacto');
    }
};
