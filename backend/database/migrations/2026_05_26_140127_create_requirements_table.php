<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('core.requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Datos Básicos
            $table->string('rrti')->unique(); 
            $table->string('requirement_type'); // Tipo de Requerimiento
            $table->date('creation_date'); // Fecha de creación manual
            $table->text('description'); // Descripción detallada
            $table->string('management_type'); // Tipo de gestión
            
            // Archivos Adjuntos (Guardamos la ruta del storage, no el archivo físico en la BD)
            $table->string('needs_spreadsheet_path')->nullable(); // Planilla de necesidades
            $table->string('it_request_doc_path')->nullable(); // Documento de Solicitud TI

            // ==========================================
            // COLUMNAS DEL UNIT SNAPSHOT (INMUTABILIDAD)
            // ==========================================
            $table->string('snapshot_society_name');
            $table->string('snapshot_system_name');
            $table->string('snapshot_unit_name');

            // Relación con el Consultor Funcional (Esto trae mágicamente su persona, unidad, sistema, etc.)
            $table->foreignUuid('functional_consultant_id')
                  ->constrained('security.functional_consultants')
                  ->onDelete('restrict'); // 'restrict' evita que se borre un consultor si tiene requerimientos asignados
            
            // Control de Estados y Auditoría
            $table->string('status')->default('PL'); 
            $table->boolean('is_locked')->default(false); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
