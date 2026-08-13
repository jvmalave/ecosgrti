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
            $table->foreignUuid('society_id')->constrained('catalogs.societies')->onDelete('restrict');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['society_id', 'name'], 'uk_systems_society_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs.systems');
    }
};