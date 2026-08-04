<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.dt_registers', function (Blueprint $table) {
            // Clave Primaria UUID
            $table->uuid('id')->primary();
            
            // Relación con el componente Rol de DT (Usando UUID)
            $table->foreignUuid('role_id')
                  ->constrained('workflow.dt_roles')
                  ->onDelete('cascade'); // RN-05: Limpieza en cascada
                  
            // Datos de la Bitácora técnica
            $table->string('title');
            $table->date('date');
            $table->text('description');
            
            $table->timestamps();

            // RN-04: Garantía de Unicidad Concurrente (No colisión de títulos en el mismo Rol)
            $table->unique(['role_id', 'title'], 'uq_workflow_dt_registers_role_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.dt_registers');
    }
};