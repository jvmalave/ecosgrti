<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogs.requesting_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Llave foránea hacia la tabla systems dentro del mismo esquema
            $table->foreignUuid('system_id')->constrained('catalogs.systems')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs.requesting_units');
    }
};