<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Crea la estructura vertical inmutable para el control de subfases y estados.
     */
    public function up(): void
    {
        Schema::create('workflow.requirement_phase_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación lógica con el requerimiento padre
            $table->uuid('requirement_id')->index();
            
            // Códigos de subfase estandarizados: 'ATF-I', 'ATF-C', 'CER-I', 'CER-C', etc.
            $table->string('phase_status_code', 30)->index();
            
            // Sello de tiempo exacto en que ocurrió la transición (Inmutable)
            $table->timestamp('transitioned_at');
            
            // Auditoría del consultor que ejecutó o validó el gate técnico
            $table->uuid('executed_by_user_id');
            
            $table->text('remarks')->nullable(); // Observaciones opcionales del hito
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow.requirement_phase_history');
    }
};