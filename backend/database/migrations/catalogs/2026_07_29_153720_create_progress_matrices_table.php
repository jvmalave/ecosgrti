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
        // Creación explícita dentro del esquema 'catalogs'
        Schema::create('catalogs.progress_matrices', function (Blueprint $table) {
            $table->uuid('id')->primary(); // RN-Aislamiento Total: Uso estricto de UUID v4
            $table->string('management_type', 20)->comment('Tipologías: ROLES, ENTREGABLES, MIXTO');
            $table->integer('version_number')->default(1)->comment('Control de Versionamiento Inmutable');
            $table->boolean('is_active')->default(true)->comment('Bandera reactiva para consultas en Caché');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs.progress_matrices');
    }
};