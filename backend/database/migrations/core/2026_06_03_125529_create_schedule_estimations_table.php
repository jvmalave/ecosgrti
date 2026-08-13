<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.schedule_estimations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('requirement_id')->unique();
            $table->foreign('requirement_id')
                  ->references('id')
                  ->on('core.requirements')
                  ->onDelete('restrict');
            
            $table->uuid('created_by');
            $table->foreign('created_by')
                  ->references('id')
                  ->on('security.users')
                  ->onDelete('restrict');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.schedule_estimations');
    }
};