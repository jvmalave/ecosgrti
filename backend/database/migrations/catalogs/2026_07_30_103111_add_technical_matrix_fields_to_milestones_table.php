<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogs.milestones', function (Blueprint $table) {
            $table->string('phase')->nullable()->comment('Fase principal según metodología WATCH');
            $table->string('phase_code')->nullable()->comment('Código de la fase (Ej. PL, ATF, DT)');
            $table->string('status_code')->nullable()->comment('Código de estado para motor de progreso (Ej. ES-R, COR-I)');
            $table->decimal('default_weight', 5, 2)->default(0)->comment('Ponderación de avance por status');
        });
    }

    public function down(): void
    {
        Schema::table('catalogs.milestones', function (Blueprint $table) {
            $table->dropColumn(['phase', 'phase_code', 'status_code', 'default_weight']);
        });
    }
};