<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security.functional_consultants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación interna en el mismo esquema (Persona)
            $table->foreignUuid('person_id')->constrained('security.persons')->onDelete('cascade');
            
            // Relación cruzada hacia el esquema Catalogs (Unidad Solicitante)
            $table->foreignUuid('requesting_unit_id')->constrained('catalogs.requesting_units')->onDelete('restrict');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security.functional_consultants');
    }
};