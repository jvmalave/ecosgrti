<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. pi_roles
        Schema::table('workflow.pi_roles', function (Blueprint $table) {
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
        });

        // 2. pi_test_users
        Schema::table('workflow.pi_test_users', function (Blueprint $table) {
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
        });

        // 3. pi_test_results
        Schema::table('workflow.pi_test_results', function (Blueprint $table) {
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
        });

        // 4. pi_registers (Ya tenía created_by y updated_by según nuestra migración original)
        Schema::table('workflow.pi_registers', function (Blueprint $table) {
            $table->uuid('deleted_by')->nullable();
        });

        // 5. pi_functional_approvals (Ya tenía created_by)
        Schema::table('workflow.pi_functional_approvals', function (Blueprint $table) {
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        // Reversión estructurada
        Schema::table('workflow.pi_roles', function (Blueprint $table) { $table->dropColumn(['created_by', 'updated_by', 'deleted_by']); });
        Schema::table('workflow.pi_test_users', function (Blueprint $table) { $table->dropColumn(['created_by', 'updated_by', 'deleted_by']); });
        Schema::table('workflow.pi_test_results', function (Blueprint $table) { $table->dropColumn(['created_by', 'updated_by', 'deleted_by']); });
        Schema::table('workflow.pi_registers', function (Blueprint $table) { $table->dropColumn(['deleted_by']); });
        Schema::table('workflow.pi_functional_approvals', function (Blueprint $table) { $table->dropColumn(['updated_by', 'deleted_by']); });
    }
};