<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\DtRole;
use App\Domains\Workflow\Models\CorRole;
use App\Domains\Workflow\Models\CorRegister;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;

/**
 * @property User $user
 * @property Requirement $requirement
 * @property RequirementRole $requirementRole
 */
beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    
    $this->user = User::factory()->create();

    // 1. Construcción del Grafo Jerárquico[cite: 6]
    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    DB::table('catalogs.societies')->insert([
        'id' => $societyId,
        'name' => 'CANTV Prueba ' . Str::random(5), 
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('catalogs.systems')->insert([
        'id' => $systemId,
        'society_id' => $societyId,
        'name' => 'Sistema Prueba ' . Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('catalogs.requesting_units')->insert([
        'id' => $unitId,
        'system_id' => $systemId,
        'name' => 'Unidad Prueba ' . Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('security.persons')->insert([
        'id' => $personId,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'consultor_' . Str::random(5) . '@cantv.com.ve', 
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('security.functional_consultants')->insert([
        'id'                 => $consultantId,
        'person_id'          => $personId,
        'requesting_unit_id' => $unitId,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    // 2. Inyección del ID del consultor real en el requerimiento[cite: 6]
    $this->requirement = Requirement::factory()->create([
        'status' => 'DT-C', // El requerimiento ya debe haber pasado DT
        'functional_consultant_id' => $consultantId
    ]);

    $this->requirementRole = RequirementRole::factory()->create([
        'requirement_id' => $this->requirement->id,
        'role_name' => 'Arquitecto de Software'
    ]);
});

test('CU-034: un consultor puede inicializar y sincronizar roles en Construcción-Roles migrando desde DT', function () {
    withoutMiddleware(); // Bypass absoluto para aislar la lógica del controlador[cite: 6]
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Creamos un rol en DT en estado CLOSED para que el Gatekeeper permita pasar
    DtRole::create([
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'name' => 'Arquitecto de Software',
        'status' => 'CLOSED'
    ]);

    $response = getJson("/api/workflow/requirements/{$this->requirement->id}/cor/roles-init");

    $response->assertStatus(200)
            ->assertJsonStructure([
                'requirement_id',
                'roles_list' => [
                    '*' => ['id', 'requirement_id', 'requirement_role_id', 'name', 'status']
                ]
            ]);

    $this->assertDatabaseHas('workflow.cor_roles', [
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'IN_PROGRESS'
    ]);

    // Prueba de idempotencia[cite: 6]
    $responseIdempotente = getJson("/api/workflow/requirements/{$this->requirement->id}/cor/roles-init");
    $responseIdempotente->assertStatus(200);
    
    $rolesCount = CorRole::where('requirement_id', $this->requirement->id)->count();
    expect($rolesCount)->toBe(1);
});

test('CU-038: deniega el cierre de un rol en Construcción si no posee registros en su bitácora técnica', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $corRole = CorRole::create([
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'name' => 'Arquitecto de Software',
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id, 
        'updated_by' => $this->user->id 
    ]);

    $response = patchJson("/api/workflow/cor/roles/{$corRole->id}/status", [
        'new_status' => 'CLOSED' // Adaptado a la nueva abstracción
    ]);

    $response->assertStatus(422);
});

test('CU-038: permite cerrar un rol en Construcción si posee documentación técnica', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $corRole = CorRole::create([
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'name' => 'Arquitecto de Software',
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id, 
        'updated_by' => $this->user->id  
    ]);

    CorRegister::create([
        'role_id' => $corRole->id,
        'title' => 'Implementación de Repositorios',
        'date' => now()->format('Y-m-d'),
        'description' => 'Desarrollo del patrón repository.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    $response = patchJson("/api/workflow/cor/roles/{$corRole->id}/status", [
        'new_status' => 'CLOSED'
    ]);

    $response->assertStatus(200)
            ->assertJsonFragment(['new_status' => 'CLOSED']);
});