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
        Schema::create('workflow.cor_registers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación con la tabla padre
            $table->uuid('role_id');
            $table->foreign('role_id')->references('id')->on('workflow.cor_roles')->onDelete('cascade');

            // Datos de la bitácora
            $table->string('title', 255);
            $table->date('date');
            $table->text('description');

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
            
            // Índice opcional para optimizar las consultas del FormRequest de unicidad
            $table->index(['role_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow.cor_registers');
    }
};