<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MilestoneSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar tabla antes de sembrar para evitar duplicados en pruebas
        DB::table('catalogs.milestones')->truncate();

        $milestones = [
            // ==========================================
            // TIPOLOGÍA: ROLES
            // ==========================================
            ['phase' => 'PLANIFICACIÓN', 'phase_code' => 'PL', 'name' => 'Requerimiento Creado', 'status_code' => 'RC', 'default_weight' => 2.00, 'management_type' => 'ROLES'],
            ['phase' => 'PLANIFICACIÓN', 'phase_code' => 'PL', 'name' => 'Estimacion Registrada', 'status_code' => 'ES-R', 'default_weight' => 3.00, 'management_type' => 'ROLES'],
            ['phase' => 'ANÁLISIS TÉCNICO FUNCIONAL', 'phase_code' => 'ATF', 'name' => 'Análisis Técnico Funcional iniciado', 'status_code' => 'ATF-I', 'default_weight' => 3.00, 'management_type' => 'ROLES'],
            ['phase' => 'ANÁLISIS TÉCNICO FUNCIONAL', 'phase_code' => 'ATF', 'name' => 'Análisis Técnico Funcional Completado', 'status_code' => 'ATF-C', 'default_weight' => 7.00, 'management_type' => 'ROLES'],
            ['phase' => 'DISEÑO TÉCNICO', 'phase_code' => 'DT', 'name' => 'Diseño Técnico Iniciado', 'status_code' => 'DT-I', 'default_weight' => 3.00, 'management_type' => 'ROLES'],
            ['phase' => 'DISEÑO TÉCNICO', 'phase_code' => 'DT', 'name' => 'Diseño Técnico Completado', 'status_code' => 'DT-C', 'default_weight' => 7.00, 'management_type' => 'ROLES'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción roles Iniciado', 'status_code' => 'COR-I', 'default_weight' => 5.00, 'management_type' => 'ROLES'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción roles Completado', 'status_code' => 'COR-C', 'default_weight' => 20.00, 'management_type' => 'ROLES'],
            ['phase' => 'PRUEBAS INTEGRALES', 'phase_code' => 'PI', 'name' => 'Pruebas Integrales Iniciadas', 'status_code' => 'PI-I', 'default_weight' => 5.00, 'management_type' => 'ROLES'],
            ['phase' => 'PRUEBAS INTEGRALES', 'phase_code' => 'PI', 'name' => 'Pruebas Integrales Completadas', 'status_code' => 'PI-C', 'default_weight' => 10.00, 'management_type' => 'ROLES'],
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Roles Iniciada', 'status_code' => 'CER-I', 'default_weight' => 5.00, 'management_type' => 'ROLES'],
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Roles Completada', 'status_code' => 'CER-C', 'default_weight' => 10.00, 'management_type' => 'ROLES'],
            ['phase' => 'IMPLEMENTACIÓN', 'phase_code' => 'IMP', 'name' => 'PAP iniciado', 'status_code' => 'PAP-I', 'default_weight' => 3.00, 'management_type' => 'ROLES'],
            ['phase' => 'IMPLEMENTACIÓN', 'phase_code' => 'IMP', 'name' => 'PAP Completado', 'status_code' => 'PAP-C', 'default_weight' => 7.00, 'management_type' => 'ROLES'],
            ['phase' => 'IMPLEMENTACIÓN', 'phase_code' => 'IMP', 'name' => 'Asignación Usuarios Completado', 'status_code' => 'AU-C', 'default_weight' => 5.00, 'management_type' => 'ROLES'],
            ['phase' => 'CIERRE', 'phase_code' => 'C', 'name' => 'Requerimiento Finalizado', 'status_code' => 'RF', 'default_weight' => 5.00, 'management_type' => 'ROLES'],

            // ==========================================
            // TIPOLOGÍA: ENTREGABLE
            // ==========================================
            ['phase' => 'PLANIFICACIÓN', 'phase_code' => 'PL', 'name' => 'Requerimiento Creado', 'status_code' => 'RC', 'default_weight' => 2.00, 'management_type' => 'ENTREGABLES'],
            ['phase' => 'PLANIFICACIÓN', 'phase_code' => 'PL', 'name' => 'Estimacion Registrada', 'status_code' => 'ES-R', 'default_weight' => 3.00, 'management_type' => 'ENTREGABLES'],
            ['phase' => 'ANÁLISIS TÉCNICO FUNCIONAL', 'phase_code' => 'ATF', 'name' => 'Análisis Técnico Funcional iniciado', 'status_code' => 'ATF-I', 'default_weight' => 3.00, 'management_type' => 'ENTREGABLES'],
            ['phase' => 'ANÁLISIS TÉCNICO FUNCIONAL', 'phase_code' => 'ATF', 'name' => 'Análisis Técnico Funcional Completado', 'status_code' => 'ATF-C', 'default_weight' => 7.00, 'management_type' => 'ENTREGABLES'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción Entregable Iniciado', 'status_code' => 'COE-I', 'default_weight' => 15.00, 'management_type' => 'ENTREGABLES'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción Entregable Completado', 'status_code' => 'COE-C', 'default_weight' => 30.00, 'management_type' => 'ENTREGABLES'],
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Entregable Iniciada', 'status_code' => 'CEE-I', 'default_weight' => 10.00, 'management_type' => 'ENTREGABLES'],
            // Ajuste matemático al 20.00% para cuadrar el salto de acumulado de 70% a 90% dictado en la matriz.
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Entregable Completada', 'status_code' => 'CEE-C', 'default_weight' => 20.00, 'management_type' => 'ENTREGABLES'],
            ['phase' => 'CIERRE', 'phase_code' => 'C', 'name' => 'Requerimiento Finalizado', 'status_code' => 'RF', 'default_weight' => 10.00, 'management_type' => 'ENTREGABLES'],

            // ==========================================
            // TIPOLOGÍA: MIXTO
            // ==========================================
            ['phase' => 'PLANIFICACIÓN', 'phase_code' => 'PL', 'name' => 'Requerimiento Creado', 'status_code' => 'RC', 'default_weight' => 2.00, 'management_type' => 'MIXTO'],
            ['phase' => 'PLANIFICACIÓN', 'phase_code' => 'PL', 'name' => 'Estimacion Registrada', 'status_code' => 'ES-R', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'ANÁLISIS TÉCNICO FUNCIONAL', 'phase_code' => 'ATF', 'name' => 'Análisis Técnico Funcional iniciado', 'status_code' => 'ATF-I', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'ANÁLISIS TÉCNICO FUNCIONAL', 'phase_code' => 'ATF', 'name' => 'Análisis Técnico Funcional Completado', 'status_code' => 'ATF-C', 'default_weight' => 7.00, 'management_type' => 'MIXTO'],
            ['phase' => 'DISEÑO TÉCNICO', 'phase_code' => 'DT', 'name' => 'Diseño Técnico Iniciado', 'status_code' => 'DT-I', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'DISEÑO TÉCNICO', 'phase_code' => 'DT', 'name' => 'Diseño Técnico Completado', 'status_code' => 'DT-C', 'default_weight' => 7.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción roles Iniciado', 'status_code' => 'COR-I', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción roles Completado', 'status_code' => 'COR-C', 'default_weight' => 10.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción Entregable Iniciado', 'status_code' => 'COE-I', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CONSTRUCCIÓN', 'phase_code' => 'CO', 'name' => 'Construcción Entregable Completado', 'status_code' => 'COE-C', 'default_weight' => 9.00, 'management_type' => 'MIXTO'],
            ['phase' => 'PRUEBAS INTEGRALES', 'phase_code' => 'PI', 'name' => 'Pruebas Integrales Iniciadas', 'status_code' => 'PI-I', 'default_weight' => 5.00, 'management_type' => 'MIXTO'],
            ['phase' => 'PRUEBAS INTEGRALES', 'phase_code' => 'PI', 'name' => 'Pruebas Integrales Completadas', 'status_code' => 'PI-C', 'default_weight' => 10.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Roles Iniciada', 'status_code' => 'CER-I', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Roles Completada', 'status_code' => 'CER-C', 'default_weight' => 5.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Entregable Iniciada', 'status_code' => 'CEE-I', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CERTIFICACIÓN', 'phase_code' => 'CE', 'name' => 'Certificación Entregable Completada', 'status_code' => 'CEE-C', 'default_weight' => 4.00, 'management_type' => 'MIXTO'],
            ['phase' => 'IMPLEMENTACIÓN', 'phase_code' => 'IMP', 'name' => 'PAP iniciado', 'status_code' => 'PAP-I', 'default_weight' => 3.00, 'management_type' => 'MIXTO'],
            ['phase' => 'IMPLEMENTACIÓN', 'phase_code' => 'IMP', 'name' => 'PAP Completado', 'status_code' => 'PAP-C', 'default_weight' => 7.00, 'management_type' => 'MIXTO'],
            ['phase' => 'IMPLEMENTACIÓN', 'phase_code' => 'IMP', 'name' => 'Asignación Usuarios Completado', 'status_code' => 'AU-C', 'default_weight' => 5.00, 'management_type' => 'MIXTO'],
            ['phase' => 'CIERRE', 'phase_code' => 'C', 'name' => 'Requerimiento Finalizado', 'status_code' => 'RF', 'default_weight' => 5.00, 'management_type' => 'MIXTO'],
        ];

        foreach ($milestones as $milestone) {
            DB::table('catalogs.milestones')->insert([
                'id'              => (string) Str::uuid(),
                'phase'           => $milestone['phase'],
                'phase_code'      => $milestone['phase_code'],
                'name'            => $milestone['name'],
                'status_code'     => $milestone['status_code'],
                'default_weight'  => $milestone['default_weight'],
                'management_type' => $milestone['management_type'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}