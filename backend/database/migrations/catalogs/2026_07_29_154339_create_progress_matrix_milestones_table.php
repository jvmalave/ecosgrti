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
        Schema::create('catalogs.progress_matrix_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary(); // RN-Aislamiento Total: Uso estricto de UUID v4
            
            // Llaves foráneas lógicas / físicas dentro del mismo esquema
            $table->uuid('matrix_id')->comment('FK a catalogs.progress_matrices');
            $table->uuid('milestone_id')->comment('FK a catalogs.milestones');
            
            // RN-Precisión Decimal: DECIMAL(5,2) para almacenar valores como 100.00
            $table->decimal('weight_percentage', 5, 2)->comment('Ponderación porcentual del hito');
            
            $table->timestamps();

            // Restricción de integridad a nivel de base de datos (dentro del mismo esquema)
            $table->foreign('matrix_id')
                  ->references('id')
                  ->on('catalogs.progress_matrices')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs.progress_matrix_milestones');
    }
};