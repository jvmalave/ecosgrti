<?php

use App\Domains\Core\Models\Requirement;
use App\Domains\Core\Services\RequirementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createHierarchicalRequirementSnapshot(?string $matrixId = null, string $managementType = 'Roles', string $status = 'ATF-I'): Requirement
{
    $societyId = (string) Str::uuid();
    $systemId = (string) Str::uuid();
    $requestingUnitId = (string) Str::uuid();
    $personId = (string) Str::uuid();
    $functionalConsultantId = (string) Str::uuid();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SISTEMA CORE', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $requestingUnitId, 'system_id' => $systemId, 'name' => 'Unidad de Pruebas', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'Consultor', 'last_name' => 'Test', 'email' => 'test.snapshot@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $functionalConsultantId, 'person_id' => $personId, 'requesting_unit_id' => $requestingUnitId, 'created_at' => now(), 'updated_at' => now()]);

    $requirement = new Requirement();
    $requirement->id = (string) Str::uuid();
    $requirement->rrti = 'RRTI-' . rand(1000, 9999);
    $requirement->requirement_type = 'NUEVO SISTEMA';
    $requirement->creation_date = Carbon::now()->subDays(2)->toDateString();
    $requirement->description = 'Test Snapshot Inmutable';
    $requirement->management_type = $managementType;
    $requirement->snapshot_society_name = 'CANTV';
    $requirement->snapshot_system_name = 'SISTEMA CORE';
    $requirement->snapshot_unit_name = 'Unidad de Pruebas';
    $requirement->functional_consultant_id = $functionalConsultantId;
    $requirement->status = $status;
    $requirement->is_locked = false;
    $requirement->progress_matrix_id = $matrixId;
    $requirement->created_at = Carbon::now()->subDays(2);
    $requirement->save();

    return $requirement;
}

function createMatrixSnapshot(string $type): string
{
    // FIX: Desactivar cualquier matriz existente del mismo tipo generada por seeders/migraciones
    DB::table('catalogs.progress_matrices')
        ->where('management_type', $type)
        ->update(['is_active' => false]);

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

it('re-enlaza exitosamente una nueva matriz dinámica al cambiar el tipo de gestión en la fase ATF', function () {
    $requirementService = app(RequirementService::class);
    
    $oldMatrixId = createMatrixSnapshot('Roles');
    $newMatrixId = createMatrixSnapshot('Entregable');

    // Se crea en estado ATF-I, permitido para alteraciones dinámicas de matriz
    $requirement = createHierarchicalRequirementSnapshot($oldMatrixId, 'Roles', 'ATF-I');
    
    $userId = (string) Str::uuid();

    $updatedRequirement = $requirementService->updateManagementTypeAtf($requirement->id, 'Entregable', $userId);

    expect($updatedRequirement->management_type)->toEqual('Entregable')
        ->and($updatedRequirement->progress_matrix_id)->toEqual($newMatrixId);
});

it('lanza una excepción (Hard Gate) si se intenta cambiar la matriz cuando la fase ATF ya está cerrada', function () {
    $requirementService = app(RequirementService::class);
    
    $matrixId = createMatrixSnapshot('Roles');

    // Se fuerza la fase ATF-C para gatillar el error de la máquina de estados
    $requirement = createHierarchicalRequirementSnapshot($matrixId, 'Roles', 'ATF-C');

    $userId = (string) Str::uuid();

    $requirementService->updateManagementTypeAtf($requirement->id, 'Mixto', $userId);
})->throws(Exception::class, 'Hard Gate Activo');