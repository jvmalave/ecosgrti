<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.coe_deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación con el Requerimiento
            $table->foreignUuid('req_id')->constrained('core.requirements')->cascadeOnDelete();
            
            // 🟢 VINCULACIÓN DIRECTA AL MAESTRO: Apuntamos a la tabla de origen real
            $table->foreignUuid('deliverable_id')->constrained('workflow.deliverables')->cascadeOnDelete();
            
            // Estado del Ciclo de Vida del Entregable (Inicia en proceso)
            $table->string('status', 50)->default('IN_PROGRESS');
            
            // Trazabilidad Forense
            $table->foreignUuid('created_by')->nullable()->constrained('security.users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('security.users')->nullOnDelete();
            $table->foreignUuid('deleted_by')->nullable()->constrained('security.users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['req_id', 'status']);
            // Idempotencia: Evita que el mismo entregable maestro se duplique en la fase COE
            $table->unique(['req_id', 'deliverable_id'], 'coe_deliverables_unique_master_per_req');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.coe_deliverables');
    }
};