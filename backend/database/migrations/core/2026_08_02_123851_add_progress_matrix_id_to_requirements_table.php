<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        // Agregamos la columna 'progress_matrix_id' a la tabla 'requirements' y establecemos la relación con la tabla 'progress_matrices'
        // Se añade el campo como nullable inicialmente para no romper registros existentes.
        // En un entorno de producción con datos, se requeriría un script de migración de datos previo.
        Schema::table('core.requirements', function (Blueprint $table) {
            $table->uuid('progress_matrix_id')->nullable()->after('current_phase');
            
            
            $table->foreign('progress_matrix_id')
                  ->references('id')
                  ->on('catalogs.progress_matrices')
                  ->restrictOnDelete();
        });
    }

   
    public function down(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            $table->dropForeign(['progress_matrix_id']);
            $table->dropColumn('progress_matrix_id');
        });
    }
};