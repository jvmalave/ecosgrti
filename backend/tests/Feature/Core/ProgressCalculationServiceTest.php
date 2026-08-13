<?php

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Services\ProgressCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper para construir el Grafo Jerárquico exigido por las llaves foráneas.
 */
function createHierarchicalRequirementProgress(?string $matrixId = null, string $managementType = 'Mixto'): Requirement
{
    $societyId = (string) Str::uuid();
    $systemId = (string) Str::uuid();
    $requestingUnitId = (string) Str::uuid();
    $personId = (string) Str::uuid();
    $functionalConsultantId = (string) Str::uuid();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SISTEMA CORE', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $requestingUnitId, 'system_id' => $systemId, 'name' => 'Unidad de Pruebas', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'Consultor', 'last_name' => 'Test', 'email' => 'test.progreso@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $functionalConsultantId, 'person_id' => $personId, 'requesting_unit_id' => $requestingUnitId, 'created_at' => now(), 'updated_at' => now()]);

    $requirement = new Requirement();
    $requirement->id = (string) Str::uuid();
    $requirement->rrti = 'RRTI-' . rand(1000, 9999);
    $requirement->requirement_type = 'NUEVO SISTEMA';
    $requirement->creation_date = Carbon::now()->subDays(2)->toDateString();
    $requirement->description = 'Test Cálculo de Progreso';
    $requirement->management_type = $managementType;
    $requirement->snapshot_society_name = 'CANTV';
    $requirement->snapshot_system_name = 'SISTEMA CORE';
    $requirement->snapshot_unit_name = 'Unidad de Pruebas';
    $requirement->functional_consultant_id = $functionalConsultantId;
    $requirement->status = 'RC';
    $requirement->is_locked = false;
    $requirement->progress_matrix_id = $matrixId;
    $requirement->created_at = Carbon::now()->subDays(2);
    $requirement->save();

    return $requirement;
}

function createMatrixProgress(string $type = 'Mixto'): string
{
    $matrixId = (string) Str::uuid();
    DB::table('catalogs.progress_matrices')->insert([
        'id' => $matrixId,
        'management_type' => $type,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    return $matrixId;
}

it('calcula el progreso exactamente al 100% usando el snapshot inmutable de la matriz (MDM)', function () {
    $progressService = app(ProgressCalculationService::class);
    $matrixId = createMatrixProgress('Mixto');
    $userId = (string) Str::uuid(); 
    
    $rcMilestoneId = (string) Str::uuid();
    $atfcMilestoneId = (string) Str::uuid();
    $fcMilestoneId = (string) Str::uuid();

    DB::table('catalogs.milestones')->insert([
        ['id' => $rcMilestoneId, 'management_type' => 'Mixto', 'name' => 'Registro', 'phase' => 'Planificación', 'phase_code' => 'PL', 'status_code' => 'RC', 'default_weight' => 10.0, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $atfcMilestoneId, 'management_type' => 'Mixto', 'name' => 'Cierre ATF', 'phase' => 'Análisis', 'phase_code' => 'ATF', 'status_code' => 'ATF-C', 'default_weight' => 40.0, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $fcMilestoneId, 'management_type' => 'Mixto', 'name' => 'Cierre', 'phase' => 'Cierre', 'phase_code' => 'FC', 'status_code' => 'FC', 'default_weight' => 50.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Retirado el sort_order para compatibilidad con la base de datos de pruebas
    DB::table('catalogs.progress_matrix_milestones')->insert([
        ['id' => (string) Str::uuid(), 'matrix_id' => $matrixId, 'milestone_id' => $rcMilestoneId, 'weight_percentage' => 10.0, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'matrix_id' => $matrixId, 'milestone_id' => $atfcMilestoneId, 'weight_percentage' => 40.0, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'matrix_id' => $matrixId, 'milestone_id' => $fcMilestoneId, 'weight_percentage' => 50.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $requirement = createHierarchicalRequirementProgress($matrixId, 'Mixto');

    DB::table('workflow.requirement_phase_history')->insert([
        ['id' => (string) Str::uuid(), 'requirement_id' => $requirement->id, 'phase_status_code' => 'RC', 'transitioned_at' => now(), 'executed_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'requirement_id' => $requirement->id, 'phase_status_code' => 'ATF-C', 'transitioned_at' => now(), 'executed_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'requirement_id' => $requirement->id, 'phase_status_code' => 'FC', 'transitioned_at' => now(), 'executed_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $progress = $progressService->calculateGlobalProgress($requirement);

    expect($progress)->toBeFloat()->toEqual(100.0);
});

it('utiliza el fallback legacy de configuración cuando el requerimiento no tiene snapshot anclado', function () {
    $progressService = app(ProgressCalculationService::class);
    $userId = (string) Str::uuid();
    
    $requirement = createHierarchicalRequirementProgress(null, 'Roles');

    DB::table('workflow.requirement_phase_history')->insert([
        ['id' => (string) Str::uuid(), 'requirement_id' => $requirement->id, 'phase_status_code' => 'RC', 'transitioned_at' => now(), 'executed_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]
    ]);

    $expectedValue = (float) config('workflow_progress.matrices.Roles.RC', 2.0);

    $progress = $progressService->calculateGlobalProgress($requirement);

    expect($progress)->toBeFloat()->toEqual($expectedValue);
});

it('previene el desborde aritmético normalizando el progreso a un máximo de 100', function () {
    $progressService = app(ProgressCalculationService::class);
    $matrixId = createMatrixProgress('Roles');
    $userId = (string) Str::uuid();
    
    $rcMilestoneId = (string) Str::uuid();
    DB::table('catalogs.milestones')->insert([
        ['id' => $rcMilestoneId, 'management_type' => 'Roles', 'name' => 'Registro', 'phase' => 'Planificación', 'phase_code' => 'PL', 'status_code' => 'RC', 'default_weight' => 10.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Retirado el sort_order para compatibilidad con la base de datos de pruebas
    DB::table('catalogs.progress_matrix_milestones')->insert([
        ['id' => (string) Str::uuid(), 'matrix_id' => $matrixId, 'milestone_id' => $rcMilestoneId, 'weight_percentage' => 150.0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $requirement = createHierarchicalRequirementProgress($matrixId, 'Roles');
    
    DB::table('workflow.requirement_phase_history')->insert([
        ['id' => (string) Str::uuid(), 'requirement_id' => $requirement->id, 'phase_status_code' => 'RC', 'transitioned_at' => now(), 'executed_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]
    ]);

    $progress = $progressService->calculateGlobalProgress($requirement);

    expect($progress)->toEqual(100.0);
});