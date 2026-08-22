<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.pi_test_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pi_register_id');
            $table->uuid('test_user_id');
            $table->timestamps();

            $table->foreign('pi_register_id')
                  ->references('id')
                  ->on('workflow.pi_registers')
                  ->onDelete('cascade');
                  
            // Restrict on delete para proteger la evidencia de pruebas de usuarios huérfanos
            $table->foreign('test_user_id')
                  ->references('id')
                  ->on('workflow.pi_test_users')
                  ->onDelete('restrict'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.pi_test_results');
    }
};