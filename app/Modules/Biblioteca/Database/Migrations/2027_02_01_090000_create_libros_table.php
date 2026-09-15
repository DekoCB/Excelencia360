<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libros', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->string('autor', 150);
            $table->string('isbn', 20)->nullable();
            $table->string('categoria', 80)->nullable();
            $table->string('editorial', 120)->nullable();
            $table->unsignedSmallInteger('anio_publicacion')->nullable();
            $table->timestamps();

            $table->index('titulo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('libros');
    }
};
