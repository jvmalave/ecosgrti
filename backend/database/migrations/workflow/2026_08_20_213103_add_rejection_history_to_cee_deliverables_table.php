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
        Schema::table('workflow.cee_deliverables', function (Blueprint $table) {
            
            $table->json('rejection_history')->nullable()->after('rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow.cee_deliverables', function (Blueprint $table) {
            $table->dropColumn('rejection_history');
        });
    }
};
