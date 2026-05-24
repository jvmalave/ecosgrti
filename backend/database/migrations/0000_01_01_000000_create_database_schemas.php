<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Creamos los esquemas base
        DB::statement('CREATE SCHEMA IF NOT EXISTS security;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS catalogs;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS core;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS audit;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS ia;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS workflow;');

        // 2. Habilitamos la extensión UUID
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminamos los esquemas en caso de rollback (en cascada elimina las tablas dentro)
        DB::statement('DROP SCHEMA IF EXISTS security CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS catalogs CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS core CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS audit CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS ia CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS workflow CASCADE;');
    }
};