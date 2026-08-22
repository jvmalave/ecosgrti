<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.pi_functional_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pi_role_id')->unique(); // Relación 1 a 1 por cada rol
            $table->string('approver_name', 255);
            $table->date('date');
            
            // Metadatos del archivo multipart
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->integer('file_size'); // Almacenado en bytes
            
            $table->uuid('created_by');
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pi_role_id')
                  ->references('id')
                  ->on('workflow.pi_roles')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.pi_functional_approvals');
    }
};