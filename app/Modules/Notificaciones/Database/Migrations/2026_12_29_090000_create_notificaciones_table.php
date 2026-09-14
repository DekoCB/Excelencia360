<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('titulo', 200);
            $table->string('url')->nullable();
            $table->dateTime('leida_en')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'leida_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
