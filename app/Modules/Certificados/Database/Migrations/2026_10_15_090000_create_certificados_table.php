<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->string('numero')->unique();
            // Sin unique(): un duplicado reutiliza a propósito el mismo
            // código del certificado original para que ambos verifiquen
            // igual — ver CertificadoService::duplicar().
            $table->string('codigo_verificacion', 12)->index();
            $table->boolean('es_duplicado')->default(false);
            $table->foreignId('certificado_original_id')->nullable()->constrained('certificados')->nullOnDelete();
            $table->foreignId('emitido_por')->constrained('users')->cascadeOnDelete();
            $table->dateTime('fecha_emision');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};
