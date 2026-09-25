<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================================
        // FASE PAP: PASE A PRODUCCIÓN - Órdenes de Transporte 
        // =========================================================================
        Schema::create('workflow.pap_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id'); 
            $table->string('order_number')->unique(); // Número de la Orden de Transporte
            $table->date('date');
            $table->string('file_path'); 
            $table->string('status')->default('ORD_IN_PROGRESS'); 
            $table->string('result_category')->nullable(); 
            $table->string('result_file')->nullable(); 
            
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');
        });

        // =========================================================================
        // FASE PAP: PASE A PRODUCCIÓN - Roles
        // =========================================================================
        Schema::create('workflow.pap_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->uuid('requirement_role_id'); 
            $table->uuid('order_id')->nullable(); // Vinculación dinámica a pap_orders
            
            // Segregación Tripartita de PAP
            $table->enum('status', ['PENDING_PAP', 'IN_PROGRESS', 'IN_PRODUCTION'])->default('PENDING_PAP');
            
            $table->text('fail_reason')->nullable(); // Justificación de no despliegue
            $table->json('rejection_history')->nullable(); // Historial inmutable en JSON
            
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Llaves foráneas calcadas de CER
            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');
            $table->foreign('requirement_role_id')->references('id')->on('workflow.requirements_roles')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('workflow.pap_orders')->onDelete('set null');
            
            // Evitar duplicidad del mismo rol en la fase PAP
            $table->unique(['requirement_role_id', 'requirement_id'], 'pap_roles_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.pap_roles');
        Schema::dropIfExists('workflow.pap_orders');
    }
};