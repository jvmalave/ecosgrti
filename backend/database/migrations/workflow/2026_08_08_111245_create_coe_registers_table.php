<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.coe_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación con el Entregable en Proceso
            $table->foreignUuid('coe_deliverable_id')->constrained('workflow.coe_deliverables')->cascadeOnDelete();
            
            // Datos de la Bitácora
            $table->string('title');
            $table->date('date');
            $table->text('description');
            
            // Trazabilidad Forense
            $table->foreignUuid('created_by')->nullable()->constrained('security.users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('security.users')->nullOnDelete();
            $table->foreignUuid('deleted_by')->nullable()->constrained('security.users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();

            // RN-04: Prevención de duplicidad concurrente de títulos por entregable
            $table->unique(
                ['coe_deliverable_id', 'title'], 
                'coe_registers_unique_title_per_deliverable'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.coe_activities');
    }
};