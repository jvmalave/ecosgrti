<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::table('workflow.requirements_roles', function (Blueprint $table) {
        // Eliminar el índice anterior (asumiendo que se llama 'req_role_unique')
        $table->dropUnique('req_role_unique'); 
    });

    // Crear índice parcial
    DB::statement('CREATE UNIQUE INDEX req_role_unique ON workflow.requirements_roles (requirement_id, role_name) WHERE deleted_at IS NULL');
}

public function down(): void
{
    DB::statement('DROP INDEX workflow.req_role_unique');
    Schema::table('workflow.requirements_roles', function (Blueprint $table) {
        $table->unique(['requirement_id', 'role_name'], 'req_role_unique');
    });
}
};
