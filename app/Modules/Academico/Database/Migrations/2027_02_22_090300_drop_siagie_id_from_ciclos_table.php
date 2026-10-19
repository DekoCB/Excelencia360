<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('siagie_id');
        });
    }

    public function down(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->foreignId('siagie_id')->nullable()->after('estado')->constrained('siagies')->nullOnDelete();
        });
    }
};
