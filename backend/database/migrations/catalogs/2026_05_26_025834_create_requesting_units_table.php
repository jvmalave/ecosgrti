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
            $table->foreignUuid('system_id')->constrained('catalogs.systems')->onDelete('restrict');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['system_id', 'name'], 'uk_units_system_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs.requesting_units');
    }
};