<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\CorRole;
use App\Domains\Workflow\Models\PiRole;
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

    // 1. Construcción del Grafo Jerárquico
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

    // 2. Inyección del ID del consultor real en el requerimiento
    // Asumimos que el requerimiento se encuentra listo para iniciar PI (ej. status COR-C o PI-I)
    $this->requirement = Requirement::factory()->create([
        'status' => 'COR-C', 
        'functional_consultant_id' => $consultantId
    ]);

    $this->requirementRole = RequirementRole::factory()->create([
        'requirement_id' => $this->requirement->id,
        'role_name' => 'QA Tester Funcional'
    ]);
});

test('CU-040: un consultor puede inicializar y sincronizar roles en Pruebas Integrales migrando desde COR', function () {
    withoutMiddleware(); 
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Creamos un rol en COR en estado CLOSED para que el Gatekeeper permita inicializar PI
    CorRole::create([
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'name' => 'QA Tester Funcional',
        'status' => 'CLOSED',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    // Llamada al endpoint unificado que acabamos de refactorizar en PiRoleController
    $response = getJson("/api/workflow/requirements/{$this->requirement->id}/pi/roles-init");

    $response->assertStatus(200)
            ->assertJsonStructure([
                'requirement_id',
                'roles_list' => [
                    '*' => ['id', 'requirement_id', 'requirement_role_id', 'name', 'status', 'is_approved']
                ]
            ]);

    // Verificamos que se haya insertado en la tabla correcta con is_approved en false
    $this->assertDatabaseHas('workflow.pi_roles', [
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'IN_PROGRESS',
        'is_approved' => false
    ]);

    // Prueba de idempotencia
    $responseIdempotente = getJson("/api/workflow/requirements/{$this->requirement->id}/pi/roles-init");
    $responseIdempotente->assertStatus(200);
    
    $rolesCount = PiRole::where('requirement_id', $this->requirement->id)->count();
    expect($rolesCount)->toBe(1);
});

test('CU-PI-GATE: deniega el cierre de un rol en Pruebas Integrales si no cuenta con aprobación funcional', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // Creamos un rol en PI sin aprobación (is_approved = false)
    $piRole = PiRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'IN_PROGRESS',
        'is_approved' => false,
        'created_by' => $this->user->id, 
        'updated_by' => $this->user->id  
    ]);

    // Intentamos cerrarlo mediante el endpoint polimórfico de la clase abstracta
    $response = patchJson("/api/workflow/pi/roles/{$piRole->id}/status", [
        'new_status' => 'CLOSED' 
    ]);

    // Debe arrojar 422 (Unprocessable Entity) por la regla de negocio de validateStatusTransition
    $response->assertStatus(422);
});

test('CU-PI-SUCCESS: permite cerrar un rol en Pruebas Integrales si ya está aprobado', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // Creamos un rol en PI YA APROBADO (simulando que el PiApprovalService hizo su trabajo)
    $piRole = PiRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'IN_PROGRESS',
        'is_approved' => true, // 🟢 La llave del éxito
        'created_by' => $this->user->id, 
        'updated_by' => $this->user->id  
    ]);

    $response = patchJson("/api/workflow/pi/roles/{$piRole->id}/status", [
        'new_status' => 'CLOSED'
    ]);

    // Verificamos que pase exitosamente
    $response->assertStatus(200)
            ->assertJsonFragment(['new_status' => 'CLOSED']);
            
    $this->assertDatabaseHas('workflow.pi_roles', [
        'id' => $piRole->id,
        'status' => 'CLOSED'
    ]);
});

test('CU-PI-PHASE-GATE: bloquea el cierre global de la fase PI si las fases predecesoras no están cerradas en el historial', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // Asumimos que el requerimiento NO tiene historial de ATF-C, DT-C y COR-C
    
    $piRole = PiRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'CLOSED', 
        'is_approved' => true,
        'created_by' => $this->user->id, 
        'updated_by' => $this->user->id  
    ]);

    // Intentamos ejecutar el cierre global de la fase
    $response = patchJson("/api/workflow/requirements/{$this->requirement->id}/pi/close");

    // 🟢 Aseguramos el código 422 y buscamos las palabras clave del Hard Gate
    $response->assertStatus(422)
            ->assertSee('ATF-C')
            ->assertSee('DT-C')
            ->assertSee('COR-C')
            ->assertSee('abiertas');
});