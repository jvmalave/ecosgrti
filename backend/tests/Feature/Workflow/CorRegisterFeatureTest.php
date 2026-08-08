<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\CorRole;
use App\Domains\Workflow\Models\CorRegister;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\withoutMiddleware;

/**
 * @property User $user
 * @property Requirement $requirement
 * @property RequirementRole $requirementRole
 * @property CorRole $corRole
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
        'id' => $societyId, 'name' => 'CANTV Prueba ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()
    ]);
    DB::table('catalogs.systems')->insert([
        'id' => $systemId, 'society_id' => $societyId, 'name' => 'Sistema Prueba', 'created_at' => now(), 'updated_at' => now()
    ]);
    DB::table('catalogs.requesting_units')->insert([
        'id' => $unitId, 'system_id' => $systemId, 'name' => 'Unidad Prueba', 'created_at' => now(), 'updated_at' => now()
    ]);
    DB::table('security.persons')->insert([
        'id' => $personId, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'consultor_' . Str::random(5) . '@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()
    ]);
    DB::table('security.functional_consultants')->insert([
        'id' => $consultantId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()
    ]);

    $this->requirement = Requirement::factory()->create([
        'status' => 'DT-C', // Estado previo
        'functional_consultant_id' => $consultantId
    ]);
    
    $this->requirementRole = RequirementRole::factory()->create([
        'requirement_id' => $this->requirement->id,
        'role_name' => 'Especialista de Base de Datos'
    ]);

    // Se crea directamente el rol de COR para probar la bitácora
    $this->corRole = CorRole::create([
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'name' => 'Especialista de Base de Datos',
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);
});

test('CU-036: almacena un registro de bitácora y dispara el hito de avance global COR-I', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $payload = [
        'title' => 'Desarrollo de Migraciones',
        'date' => now()->format('Y-m-d'),
        'description' => 'Codificación de las tablas en PostgreSQL.'
    ];

    $response = postJson("/api/workflow/requirements/{$this->requirement->id}/cor/roles/{$this->corRole->id}/registers", $payload);

    $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Desarrollo de Migraciones']);
            
    // Valida que se haya cambiado el estado a COR-I
    $this->assertDatabaseHas('core.requirements', [
        'id' => $this->requirement->id,
        'status' => 'COR-I'
    ]);
});

test('CU-037: previene duplicidad concurrente de títulos en la bitácora de un mismo rol', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    CorRegister::create([
        'role_id' => $this->corRole->id,
        'title' => 'Script de Semillas (Seeders)',
        'date' => now()->format('Y-m-d'),
        'description' => 'Definición inicial.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    $payload = [
        'title' => 'Script de Semillas (Seeders)',
        'date' => now()->format('Y-m-d'),
        'description' => 'Intento de duplicidad en Construcción.'
    ];

    $response = postJson("/api/workflow/requirements/{$this->requirement->id}/cor/roles/{$this->corRole->id}/registers", $payload);

    $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
});

test('CU-037: permite actualizar un registro respetando el saneamiento y la nueva ruta', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $register = CorRegister::create([
        'role_id' => $this->corRole->id,
        'title' => 'Versionado Inicial COR',
        'date' => now()->format('Y-m-d'),
        'description' => 'Texto anterior.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    // Nota la ruta actualizada con /roles/{role_id}
    $response = putJson("/api/workflow/cor/registers/{$register->id}/roles/{$this->corRole->id}", [
        'title' => 'Versionado Inicial COR', // Se envía para validar la regla unique ignorando su propio ID
        'date' => now()->format('Y-m-d'),
        'description' => 'Texto de código modificado exitosamente.'
    ]);

    $response->assertStatus(200)
            ->assertJsonFragment(['description' => 'Texto de código modificado exitosamente.']);
});

test('CU-037: elimina lógicamente (soft delete) un registro de construcción', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $register = CorRegister::create([
        'role_id' => $this->corRole->id,
        'title' => 'Código Temporal',
        'date' => now()->format('Y-m-d'),
        'description' => 'Para eliminar en pruebas.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    $response = deleteJson("/api/workflow/cor/registers/{$register->id}/roles/{$this->corRole->id}");

    $response->assertStatus(200);
    
    // Verifica que el borrado haya sido un Soft Delete con el ID del usuario
    $this->assertDatabaseHas('workflow.cor_registers', [
        'id' => $register->id,
        'deleted_by' => $this->user->id
    ]);
});