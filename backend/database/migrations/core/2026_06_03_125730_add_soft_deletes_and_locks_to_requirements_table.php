<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            // Solo agregamos el borrado lógico, ya que 'is_locked' ya existe en el diseño base
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};