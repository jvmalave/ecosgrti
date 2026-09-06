<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Añadir el timestamp de caducidad a la tabla de usuarios
        Schema::table('security.users', function (Blueprint $table) {
            $table->timestamp('password_updated_at')->nullable();
        });

        // 2. Crear la tabla de historial con auditoría
        Schema::create('security.password_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('password_hash');
            $table->uuid('created_by')->nullable(); // Campo de auditoría
            $table->timestamps(); // create_at y updated_at automáticos

            // Relación con el dueño de la contraseña
            $table->foreign('user_id')
                  ->references('id')
                  ->on('security.users')
                  ->onDelete('cascade');
                  
            // Relación con el usuario que ejecutó la acción (Auditoría)
            $table->foreign('created_by')
                  ->references('id')
                  ->on('security.users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security.password_histories');
        
        Schema::table('security.users', function (Blueprint $table) {
            $table->dropColumn('password_updated_at');
        });
    }
};