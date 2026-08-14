<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\AtfAgreement;
use App\Domains\Workflow\Models\RequirementRole;
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

    // 1. Setup organizacional
    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SGRTI', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'system_id' => $systemId, 'name' => 'CSPE', 'created_at' => now(), 'updated_at' => now()]);
    
    // 2. Crear Persona PRIMERO (Sin user_id)
    DB::table('security.persons')->insert([
        'id'         => $personId, 
        'first_name' => 'John', 
        'last_name'  => 'Doe', 
        'email'      => 'test_' . Str::random(5) . '@cantv.com.ve', 
        'created_at' => now(), 
        'updated_at' => now()
    ]);

    // 3. Crear Usuario asociado a la Persona mediante person_id
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

    // 5. Autenticar globalmente en la API
    $this->actingAs($user, 'api');

    // 6. Asegurar esquema y tabla de auditoría
    DB::statement('CREATE SCHEMA IF NOT EXISTS audit');
    DB::statement('CREATE TABLE IF NOT EXISTS audit.workflow_logs (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        action VARCHAR(255),
        user_id UUID,
        requirement_id UUID,
        timestamp TIMESTAMP
    )');
});

it('rechaza el cierre de fase si no se cumple el quórum mínimo (422)', function () use (&$consultantId) {
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId, 
        'management_type'          => 'Mixto',
        'status'                   => 'ATF-I'
    ]);

    $response = $this->postJson("/api/workflow/requirements/{$req->id}/close-atf");

    $response->assertStatus(422)
            ->assertJsonValidationErrors(['quorum']);
});

it('permite el cierre de fase si se cumple el quórum (200)', function () use (&$consultantId) {
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'management_type'          => 'Mixto',
        'status'                   => 'ATF-I'
    ]);
    
    // Usamos auth()->id() para resolver el ID del usuario autenticado sin alertas de linter
    AtfAgreement::factory()->create([
        'requirement_id'        => $req->id,
        'registered_by_user_id' => auth()->id() 
    ]);
    
    RequirementRole::factory()->create(['requirement_id' => $req->id]);

    $response = $this->postJson("/api/workflow/requirements/{$req->id}/close-atf");

    $response->assertStatus(200)
            ->assertJsonPath('status', 'ATF-C');
});

it('rechaza cualquier intento de cierre si el requerimiento ya esta bloqueado', function () use (&$consultantId) {
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'status'                   => 'ATF-C',
        'is_locked'                => true
    ]);

    $response = $this->postJson("/api/workflow/requirements/{$req->id}/close-atf");

    // Esperamos un error 422 o 403 porque la validación detecta el requerimiento sellado
    $response->assertStatus(422);
});