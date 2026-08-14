<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.dt_roles', function (Blueprint $table) {
            // Clave Primaria UUID
            $table->uuid('id')->primary();
            
            // Relación con el Rol de ATF (Usando UUID)
            $table->foreignUuid('requirement_role_id')
                  ->constrained('workflow.requirements_roles')
                  ->onDelete('cascade');
            
            // Relación con el Requerimiento principal (Usando UUID)
            $table->foreignUuid('requirement_id')
                  ->constrained('core.requirements')
                  ->onDelete('cascade');
            
            // Datos del Rol en DT
            $table->string('name');
            $table->string('status', 20)->default('IN_PROGRESS'); // IN_PROGRESS, CLOSED
            
            $table->timestamps();

            // RN-02: Garantía de Idempotencia en Sincronización Inicial
            $table->unique('requirement_role_id', 'uq_workflow_dt_roles_req_role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.dt_roles');
    }
};