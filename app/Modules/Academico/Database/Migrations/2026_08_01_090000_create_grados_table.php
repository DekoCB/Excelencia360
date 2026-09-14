<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('tipo_publico', 10);
            $table->unsignedTinyInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo_publico', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grados');
    }
};
