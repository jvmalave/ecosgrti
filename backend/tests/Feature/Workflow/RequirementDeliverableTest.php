<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\Deliverable;
use App\Domains\Security\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SGRTI', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'system_id' => $systemId, 'name' => 'CSPE', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane.doe@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $consultantId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);

    // 2. Crear y Autenticar Usuario
    $user = User::factory()->create();
    $this->actingAs($user);
});

it('registra un entregable exitosamente', function () use (&$consultantId) {
    $req = Requirement::factory()->create(['functional_consultant_id' => $consultantId]);
    $payload = ['name' => 'Manual de Usuario', 'description' => 'plan para las pruebas'];

    $this->postJson("/api/workflow/requirements/{$req->id}/deliverables", $payload)
        ->assertStatus(201);

    $this->assertDatabaseHas('workflow.deliverables', ['name' => 'Manual de Usuario', 'requirement_id' => $req->id, 'description' => 'plan para las pruebas']);
});

it('rechaza el registro duplicado (422) en el mismo requerimiento', function () use (&$consultantId) {
    $req = Requirement::factory()->create(['functional_consultant_id' => $consultantId]);
    Deliverable::factory()->create(['requirement_id' => $req->id, 'name' => 'Diagrama ER', 'description' => 'Test para las pruebas']);

    $this->postJson("/api/workflow/requirements/{$req->id}/deliverables", ['name' => 'Diagrama ER', 'description' => 'Test para las pruebas'])
        ->assertStatus(422);
});

it('permite nombres de entregables iguales si pertenecen a requerimientos distintos', function () use (&$consultantId) {
    $reqA = Requirement::factory()->create(['functional_consultant_id' => $consultantId]);
    $reqB = Requirement::factory()->create(['functional_consultant_id' => $consultantId]);
    Deliverable::factory()->create(['requirement_id' => $reqA->id, 'name' => 'Matriz de Pruebas', 'description' => 'Test para las pruebas']);

    $payload = ['name' => 'Matriz de Pruebas', 'description' => 'Test para las pruebas 2'];

    $this->postJson("/api/workflow/requirements/{$reqB->id}/deliverables", $payload)
        ->assertStatus(201);
});

it('rechaza el update si el nuevo nombre colisiona con otro entregable existente', function () use (&$consultantId) {
    $req = Requirement::factory()->create(['functional_consultant_id' => $consultantId]);
    Deliverable::factory()->create(['requirement_id' => $req->id, 'name' => 'Plan de Pruebas']);
    $aEditar = Deliverable::factory()->create(['requirement_id' => $req->id, 'name' => 'Código Fuente']);

    // 💡 Ajustado: /api/workflow/deliverables/{id} (Removido /components)
    $this->putJson("/api/workflow/deliverables/{$aEditar->id}", [
        'name' => 'Plan de Pruebas', 
        'description' => 'Test para las pruebas 2'
    ])
    ->assertStatus(422);
});