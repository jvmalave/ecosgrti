<?php

// database/migrations/2026_07_01_000001_add_sort_order_to_progress_matrices_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogs.progress_matrix_milestones', function (Blueprint $table) {
            if (!Schema::hasColumn('catalogs.progress_matrix_milestones', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalogs.progress_matrix_milestones', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
