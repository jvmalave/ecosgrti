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
    Schema::create('security.cspe_consultants', function (Blueprint $table) {
        $table->uuid('id')->primary();
        
        // Se alimenta de la tabla de personas
        $table->foreignUuid('person_id')->constrained('security.persons')->onDelete('cascade');
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cspe_consultants');
    }
};
