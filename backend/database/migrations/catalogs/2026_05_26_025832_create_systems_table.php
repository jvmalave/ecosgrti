<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        
        Schema::create('catalogs.systems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Llave foránea hacia la tabla societies dentro del mismo esquema
            $table->foreignUuid('society_id')->constrained('catalogs.societies')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs.systems');
    }
};