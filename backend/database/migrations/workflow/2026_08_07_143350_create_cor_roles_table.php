<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow.cor_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación de origen y contexto
            $table->uuid('requirement_role_id')->unique()->comment('ID del rol original en la fase de Diseño Técnico (DT)');
            $table->foreign('requirement_role_id')->references('id')->on('workflow.requirements_roles')->onDelete('cascade');;

            $table->uuid('requirement_id')->comment('Llave foránea hacia el requerimiento maestro');
            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');

            // Datos de negocio
            $table->string('name', 255);
            $table->string('status', 20)->default('IN_PROGRESS');

            // Trazabilidad y Autoría (Esquema Security)
            $table->uuid('created_by');
            $table->foreign('created_by')->references('id')->on('security.users');
            
            $table->uuid('updated_by');
            $table->foreign('updated_by')->references('id')->on('security.users');
            
            $table->uuid('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('security.users');

            // Marcas de tiempo y borrado lógico
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow.cor_roles');
    }
};