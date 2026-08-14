<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\User;
use App\Domains\Workflow\Models\RequirementRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

$consultantId = '';
$user = null;

beforeEach(function () use (&$consultantId, &$user) {

    config(['logging.default' => 'stderr']);
    config(['logging.channels.audit' => ['driver' => 'null']]);

    // 1. Recrear Jerarquía Organizacional Base
    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    DB::table('catalogs.societies')->insert([
        'id' => $societyId, 'name' => 'CANTV', 'created_at' => now(), 'updated_at' => now()
    ]);
    DB::table('catalogs.systems')->insert([
        'id' => $systemId, 'society_id' => $societyId, 'name' => 'SGRTI', 'created_at' => now(), 'updated_at' => now()
    ]);
    DB::table('catalogs.requesting_units')->insert([
        'id' => $unitId, 'system_id' => $systemId, 'name' => 'CSPE', 'created_at' => now(), 'updated_at' => now()
    ]);
    
    // 2. Crear Persona PRIMERO (Sin columna user_id)
    DB::table('security.persons')->insert([
        'id'         => $personId, 
        'first_name' => 'John', 
        'last_name'  => 'Doe', 
        'email'      => 'test_' . Str::random(5) . '@cantv.com.ve', 
        'created_at' => now(), 
        'updated_at' => now()
    ]);

    // 3. Crear Usuario ASOCIADO a la Persona mediante person_id (VÍNCULO CORRECTO ER)

      $user = User::factory()->create(
        [
          'roles'=> ['Admin'],
        ]
    );

    // 4. Crear Consultor Funcional
    DB::table('security.functional_consultants')->insert([
        'id'                 => $consultantId, 
        'person_id'          => $personId, 
        'requesting_unit_id' => $unitId, 
        'created_at'         => now(), 
        'updated_at'         => now()
    ]);

    // 5. Autenticar explícitamente en el guard 'api'
    $this->actingAs($user, 'api');
});

it('registra un rol exitosamente', function () use (&$consultantId) {
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'status'                   => 'ATF-I',
    ]);
    
    $payload = ['role_name' => 'Arquitecto', 'description' => 'Test para las pruebas', 'assignment_type' => 'Full'];

    $this->postJson("/api/workflow/requirements/{$req->id}/roles", $payload)
        ->assertStatus(201);

    $this->assertDatabaseHas('workflow.requirements_roles', [
        'role_name'       => 'Arquitecto', 
        'requirement_id'  => $req->id, 
        'description'     => 'Test para las pruebas', 
        'assignment_type' => 'Full'
    ]);
});

it('rechaza el registro duplicado (422) en el mismo requerimiento', function () use (&$consultantId) {
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'status'                   => 'ATF-I',
    ]);
    RequirementRole::factory()->create(['requirement_id' => $req->id, 'role_name' => 'Duplicado']);

    $this->postJson("/api/workflow/requirements/{$req->id}/roles", ['role_name' => 'Duplicado', 'assignment_type' => 'Full'])
        ->assertStatus(422);
});

it('permite nombres de roles iguales si pertenecen a requerimientos distintos', function () use (&$consultantId) {
    $reqA = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'status'                   => 'ATF-I',
    ]);
    $reqB = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'status'                   => 'ATF-I',
    ]);
    RequirementRole::factory()->create(['requirement_id' => $reqA->id, 'role_name' => 'QA']);

    $payload = ['role_name' => 'QA', 'description' => 'Test para las pruebas', 'assignment_type' => 'Full'];

    $this->postJson("/api/workflow/requirements/{$reqB->id}/roles", $payload)
        ->assertStatus(201);
});

it('rechaza el update si el nuevo nombre colisiona con otro rol existente', function () use (&$consultantId) {
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'status'                   => 'ATF-I',
    ]);
    RequirementRole::factory()->create(['requirement_id' => $req->id, 'role_name' => 'Existente']);
    $aEditar = RequirementRole::factory()->create(['requirement_id' => $req->id, 'role_name' => 'AEditar']);

    $this->putJson("/api/workflow/roles/{$aEditar->id}", [
        'role_name'       => 'Existente', 
        'assignment_type' => 'Full', 
        'description'     => 'Test para las pruebas 2'
    ])
    ->assertStatus(422);
});