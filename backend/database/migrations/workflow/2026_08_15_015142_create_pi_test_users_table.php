<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.pi_test_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->uuid('pi_role_id');
            $table->string('identifier', 50); // Ej: POO123456
            $table->timestamps();
            $table->softDeletes(); // Requerido para el Subflujo D (Eliminación Segura)

            $table->foreign('requirement_id')
                  ->references('id')
                  ->on('core.requirements')
                  ->onDelete('cascade');
                  
            $table->foreign('pi_role_id')
                  ->references('id')
                  ->on('workflow.pi_roles')
                  ->onDelete('cascade');

            // Regla de unicidad interna (Evita duplicados en el mismo rol)
            $table->unique(['pi_role_id', 'identifier'], 'pi_test_users_role_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.pi_test_users');
    }
};