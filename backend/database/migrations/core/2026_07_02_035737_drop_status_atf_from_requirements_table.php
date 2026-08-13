<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            // Verificamos si existe bajo el nombre exacto guardado para evitar excepciones catastróficas
            if (Schema::hasColumn('core.requirements', 'status_atf')) {
                $table->dropColumn('status_atf');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            $table->string('status_atf', 20)->default('OPEN');
        });
    }
};