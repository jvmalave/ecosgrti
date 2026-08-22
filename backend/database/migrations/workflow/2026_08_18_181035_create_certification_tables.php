<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // FASE CER: CERTIFICACIÓN DE ROLES
        // =====================================================================
        
        Schema::create('workflow.cer_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->string('ticket_number')->unique(); // Generado algorítmicamente (Ej. CER-000001)
            $table->date('request_date'); 
            $table->string('file_path'); // Evidencia de Solicitud (PDF)
            $table->string('status')->default('TKT_IN_PROGRESS'); // TKT_IN_PROGRESS, TKT_CLOSED
            $table->string('result_category')->nullable(); // Total, Parcial, Rechazo
            $table->string('result_file')->nullable(); // Evidencia de Resultado (PDF)
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');
        });

        Schema::create('workflow.cer_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->uuid('requirement_role_id'); 
            $table->uuid('ticket_id')->nullable(); // Relación dinámica con el ticket en trámite
            $table->string('status')->default('PENDING_CERTIFICATION'); // PENDING_CERTIFICATION, IN_PROGRESS, CERTIFIED
            $table->text('rejection_reason')->nullable(); // Justificación de no certificación
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');
            $table->foreign('requirement_role_id')->references('id')->on('workflow.requirements_roles')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('workflow.cer_tickets')->onDelete('set null');
            
            // Garantiza que un mismo rol de catálogo no se duplique dentro de la certificación de un requerimiento
            $table->unique(['requirement_id', 'requirement_role_id']); 
        });

        // =====================================================================
        // FASE CEE: CERTIFICACIÓN DE ENTREGABLES
        // =====================================================================
        
        Schema::create('workflow.cee_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->string('ticket_number')->unique();
            $table->date('request_date'); 
            $table->string('file_path'); 
            $table->string('status')->default('TKT_IN_PROGRESS');
            $table->string('result_category')->nullable(); 
            $table->string('result_file')->nullable(); 
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');
        });

        Schema::create('workflow.cee_deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->uuid('deliverable_id'); // Referencia al entregable maestro
            $table->uuid('ticket_id')->nullable();
            $table->string('status')->default('PENDING_CERTIFICATION');
            $table->text('rejection_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');
            $table->foreign('deliverable_id')->references('id')->on('workflow.deliverables')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('workflow.cee_tickets')->onDelete('set null');
            
            $table->unique(['requirement_id', 'deliverable_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.cee_deliverables');
        Schema::dropIfExists('workflow.cee_tickets');
        Schema::dropIfExists('workflow.cer_roles');
        Schema::dropIfExists('workflow.cer_tickets');
    }
};