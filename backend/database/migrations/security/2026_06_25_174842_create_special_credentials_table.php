<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security.special_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Relación 1 a 1 con el usuario
            $table->uuid('user_id')->unique(); 
            // El PIN cifrado (NUNCA en texto plano)
            $table->string('pin_hash');
            // Control de Fuerza Bruta y Rotación
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_locked')->default(false);
            
            $table->timestamps();

            // Llave foránea (ajusta 'security.users' según tu tabla real de usuarios)
            $table->foreign('user_id')->references('id')->on('security.users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security.special_credentials');
    }
};