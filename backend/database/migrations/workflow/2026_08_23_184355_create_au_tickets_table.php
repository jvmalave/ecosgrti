<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow.au_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requirement_id')->constrained('core.requirements')->cascadeOnDelete();
            
            // RN-AU-13: Unicidad absoluta global
            $table->string('ticket_number')->unique(); 
            $table->date('request_date'); 
            $table->string('file_path'); 
            
            $table->string('status')->default('TKT_IN_PROGRESS');
            $table->string('result_category')->nullable();
            $table->string('result_file')->nullable();
            
            // Auditoría básica
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow.au_tickets');
    }
};