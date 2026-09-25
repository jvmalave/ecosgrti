<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            $table->date('notification_date')->nullable()->comment('Fecha de Notificación (CSPE)');
            $table->date('completion_date')->nullable()->comment('Fecha Fin de Atención');
            $table->string('closure_act_path')->nullable()->comment('Ruta física del Acta de Cierre en Storage');
            $table->string('notification_support_path')->nullable()->comment('Ruta del soporte en PDF subido por el usuario');
            $table->boolean('conformity_declaration')->default(false)->comment('Sello lógico de la declaración jurada');
        });
    }

    public function down(): void
    {
        Schema::table('core.requirements', function (Blueprint $table) {
            $table->dropColumn([
                'notification_date',
                'completion_date',
                'closure_act_path',
                'notification_support_path',
                'conformity_declaration'
            ]);
        });
    }
};