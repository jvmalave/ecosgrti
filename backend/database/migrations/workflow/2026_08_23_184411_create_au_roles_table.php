<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.au_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requirement_id')->constrained('core.requirements')->cascadeOnDelete();
            $table->foreignUuid('requirement_role_id')->constrained('workflow.requirements_roles')->cascadeOnDelete();
            
            // Relación con el ticket (nullable porque inician en PENDING_AU sin ticket)
            $table->foreignUuid('ticket_id')->nullable()->constrained('workflow.au_tickets')->nullOnDelete();
            
            $table->string('status')->default('PENDING_AU');
            
            // Campo específico de AU para el PDF individual
            $table->string('planilla_path')->nullable(); 
            
            // Homologados para asimilación automática del Trait
            $table->text('rejection_reason')->nullable();
            $table->jsonb('rejection_history')->nullable();
            
            // Auditoría básica
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.au_roles');
    }
};