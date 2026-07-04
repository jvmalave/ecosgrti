<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración del esquema workflow para acuerdos ATF.
     * Garantiza el tipado fuerte y la indexación para la consulta fresca del CU-008.
     */
    public function up(): void
    {
        Schema::create('workflow.atf_agreements', function (Blueprint $table) {
            // Clave Primaria basada estrictamente en UUID v4 para alineación de microservicios
            $table->uuid('id')->primary();
            
            // Relación lógica hacia core.requirements (Shared-Nothing entre esquemas)
            $table->uuid('requirement_id')->index();
            
            $table->date('agreement_date');
            $table->text('description');
            
            // Trazabilidad y auditoría exigida por la gobernanza de CANTV
            $table->uuid('registered_by_user_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow.atf_agreements');
    }
};