<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Usamos Schema::table porque la tabla ya existe y solo añadimos la columna
        Schema::connection('pgsql')->table('audit.audit_logs', function (Blueprint $table) {
            // El target_id nos permite rastrear qué registro específico cambió
            $table->uuid('target_id')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('audit.audit_logs', function (Blueprint $table) {
            $table->dropColumn('target_id');
        });
    }
};