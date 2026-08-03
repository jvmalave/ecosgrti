<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogs.progress_matrix_milestones', function (Blueprint $table) {
            if (Schema::hasColumn('catalogs.progress_matrix_milestones', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalogs.progress_matrix_milestones', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });
    }
};