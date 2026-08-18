<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.pi_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->uuid('requirement_role_id');
            $table->string('status', 20)->default('IN_PROGRESS'); // IN_PROGRESS, CLOSED
            $table->boolean('is_approved')->default(false); // Bandera para Aprobación Funcional
            $table->timestamps();
            $table->softDeletes();

            // Llaves Foráneas inter-esquemas
            $table->foreign('requirement_id')
                  ->references('id')
                  ->on('core.requirements')
                  ->onDelete('cascade');
                  
            $table->foreign('requirement_role_id')
                  ->references('id')
                  ->on('workflow.requirements_roles')
                  ->onDelete('cascade');
                  
            // Restricción única para garantizar la idempotencia en la inserción
            $table->unique(['requirement_id', 'requirement_role_id'], 'pi_roles_req_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.pi_roles');
    }
};