<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.pi_registers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->uuid('pi_role_id');
            $table->string('title', 255);
            $table->date('date');
            $table->text('description'); // Será saneado con HTMLPurifier
            
            // Trazabilidad Forense
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('requirement_id')->references('id')->on('core.requirements')->onDelete('cascade');
            $table->foreign('pi_role_id')->references('id')->on('workflow.pi_roles')->onDelete('cascade');
            
            // Restricción de unicidad para evitar títulos duplicados en la misma bitácora del rol
            $table->unique(['pi_role_id', 'title'], 'pi_registers_role_title_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.pi_registers');
    }
};