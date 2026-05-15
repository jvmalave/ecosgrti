<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Para ejecutar comandos SQL crudos

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    // 1. Crear el esquema si no existe
    DB::statement('CREATE SCHEMA IF NOT EXISTS audit');

    // 2. Crear la tabla dentro del esquema
    Schema::create('audit.audit_logs', function (Blueprint $table) {
        $table->uuid('id')->primary(); // Usamos UUID
        $table->uuid('user_id')->nullable(); // Nullable para fallos de login
        $table->string('action'); // LOGIN_FAIL, LOGIN_SUCCESS
        $table->text('description');
        $table->ipAddress('ip_address')->nullable();
        $table->text('user_agent')->nullable();
        $table->json('payload')->nullable(); // Para auditoría detallada
        $table->timestamps();

        // Opcional: Índice para búsquedas rápidas por usuario
        $table->index('user_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit.audit_logs');
    }
};
