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
        Schema::create('core.cspe_consultant_requirement', function (Blueprint $table) {
            // ASe usa UUIDs para mantener el estándar.
            $table->uuid('id')->primary();

            // Llave hacia el Requerimiento
            $table->foreignUuid('requirement_id')
                  ->constrained('core.requirements')
                  ->onDelete('cascade'); // Si se borra el requerimiento, se borra la asignación

            // Llave hacia el Consultor CSPE
            $table->foreignUuid('cspe_consultant_id')
                  ->constrained('security.cspe_consultants')
                  ->onDelete('cascade'); // Si se borra el consultor, se borra de la asignación

            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::dropIfExists('core.cspe_consultant_requirement');
    }
};
