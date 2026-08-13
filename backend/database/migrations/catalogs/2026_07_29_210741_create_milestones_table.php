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
        // RN-Aislamiento: Creación en el esquema catalogs
        Schema::create('catalogs.milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('management_type', 20)->comment('ROLES, ENTREGABLES, MIXTO');
            $table->string('name')->comment('Nombre descriptivo del hito');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs.milestones');
    }
};