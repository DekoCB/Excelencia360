<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['estudiante_id']);
            $table->dropForeign(['matricula_id']);
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropColumn(['estudiante_id', 'matricula_id']);
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreignId('estudiante_id')->nullable()->after('id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('matricula_id')->nullable()->after('estudiante_id')->constrained('matriculas')->cascadeOnDelete();
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
