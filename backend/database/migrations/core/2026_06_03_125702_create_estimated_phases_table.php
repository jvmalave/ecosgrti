<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.estimated_phases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('schedule_estimation_id');
            $table->foreign('schedule_estimation_id')
                  ->references('id')
                  ->on('core.schedule_estimations')
                  ->onDelete('cascade');

            $table->string('phase_name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('estimated_hours', 6, 2)->unsigned();
            
            $table->unique(['schedule_estimation_id', 'phase_name']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.estimated_phases');
    }
};