<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow.deliverables', function (Blueprint $table) {
            $table->foreignUuid('created_by')->nullable()->constrained('security.users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('security.users')->nullOnDelete();
            $table->foreignUuid('deleted_by')->nullable()->constrained('security.users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow.deliverables', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['created_by', 'updated_by', 'deleted_by']);
        });
    }
};