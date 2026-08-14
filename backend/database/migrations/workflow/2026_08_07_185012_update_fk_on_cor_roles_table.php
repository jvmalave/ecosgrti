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
        Schema::table('workflow.cor_roles', function (Blueprint $table) {
            // 1. Eliminamos la restricción foránea incorrecta
            // Utilizamos el nombre exacto que generó PostgreSQL/Laravel
            $table->dropForeign('workflow_cor_roles_requirement_role_id_foreign');

            // 2. Creamos la restricción foránea correcta apuntando a requirement_roles
            $table->foreign('requirement_role_id')
                  ->references('id')
                  ->on('workflow.requirements_roles')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow.cor_roles', function (Blueprint $table) {
            // 1. Revertimos eliminando la correcta
            $table->dropForeign(['requirement_role_id']); // Laravel deduce el nombre

            // 2. Restauramos la incorrecta original (por consistencia del rollback)
            $table->foreign('requirement_role_id')
                  ->references('id')
                  ->on('workflow.dt_roles')
                  ->onDelete('cascade');
        });
    }
};