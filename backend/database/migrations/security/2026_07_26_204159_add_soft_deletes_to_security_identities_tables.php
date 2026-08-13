<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        Schema::table('security.persons', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('security.functional_consultants', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('security.cspe_consultants', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::table('security.persons', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('security.functional_consultants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('security.cspe_consultants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};