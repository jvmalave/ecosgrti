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
    Schema::table('workflow.cer_roles', function (Blueprint $table) {
        // Almacenará el arreglo histórico de rechazos
        $table->jsonb('rejection_history')->nullable()->after('rejection_reason');
    });
}

public function down(): void
{
    Schema::table('workflow.cer_roles', function (Blueprint $table) {
        $table->dropColumn('rejection_history');
    });
}
};
