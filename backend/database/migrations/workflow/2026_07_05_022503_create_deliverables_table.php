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
        Schema::create('workflow.deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->string('name');
            $table->text('description')->nullable(); // Añadido punto y coma faltante
            $table->softDeletes();
            $table->timestamps();

            // Llave foránea hacia el esquema core
            $table->foreign('requirement_id')
                  ->references('id')
                  ->on('core.requirements')
                  ->onDelete('cascade');

            $table->unique(['requirement_id', 'name'], 'req_deliverable_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow.deliverables');
    }
};