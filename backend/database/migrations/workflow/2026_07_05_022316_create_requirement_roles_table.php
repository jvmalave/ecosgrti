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
        Schema::create('workflow.requirements_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->string('role_name');
            $table->text('description')->nullable();
            $table->string('assignment_type'); 
            $table->softDeletes(); 
            $table->timestamps();

            // Llave foránea hacia el esquema core
            $table->foreign('requirement_id')
                  ->references('id')
                  ->on('core.requirements')
                  ->onDelete('cascade');

            $table->unique(['requirement_id', 'role_name'], 'req_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow.requirements_roles');
    }
};