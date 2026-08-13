<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modifica el esquema core adaptándose a la estructura existente.
     * Inserta el control métrico y optimiza las lecturas síncronas del CU-008.
     */
    public function up(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            // management_type ya existe en tu BD, solo agregamos el progreso y el estado de la subfase
            if (!Schema::hasColumn('core.requirements', 'progress_percentage')) {
                // Control métrico exacto continuo (Decimal) para barra de progreso reactiva
                $table->decimal('progress_percentage', 5, 2)->default(4.00)->after('status');
            }
            
            if (!Schema::hasColumn('core.requirements', 'status_atf')) {
                // Estado interno de la subfase ATF (OPEN, CLOSED) exigido por el Hard Gate
                $table->string('status_atf', 20)->default('OPEN')->after('progress_percentage');
            }

            // Creamos el índice compuesto WATCH utilizando tus nombres de columna reales (rrti y status)
            $table->index(['id', 'rrti', 'status'], 'idx_req_progress_lookup_v2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            $table->dropIndex('idx_req_progress_lookup_v2');
            $table->dropColumn(['progress_percentage', 'status_atf']);
        });
    }
};
