<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('security.functional_consultants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relaciones
            $table->foreignUuid('person_id')->constrained('security.persons')->onDelete('cascade');
            $table->foreignUuid('requesting_unit_id')->constrained('security.requesting_units')->onDelete('cascade');
            $table->foreignUuid('society_id')->constrained('security.societies');
            $table->foreignUuid('system_id')->constrained('security.systems');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Incluimos el esquema para que sepa exactamente qué borrar
        Schema::dropIfExists('security.functional_consultants');
    }
};