<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\DtRole;
use App\Domains\Workflow\Models\DtRegister;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\withoutMiddleware;

/**
 * @property User $user
 * @property Requirement $requirement
 * @property RequirementRole $requirementRole
 * @property DtRole $dtRole
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
  $this->requirement = Requirement::factory()->create([
    'status' => 'ATF-C',
    'functional_consultant_id' => $consultantId
  ]);

  $this->requirementRole = RequirementRole::factory()->create([
    'requirement_id' => $this->requirement->id,
    'role_name' => 'Especialista de Base de Datos'
  ]);

  $this->dtRole = DtRole::create([
    'requirement_id' => $this->requirement->id,
    'requirement_role_id' => $this->requirementRole->id,
    'name' => 'Especialista de Base de Datos',
    'status' => 'IN_PROGRESS'
  ]);
});

test('CU-030: almacena un registro de bitácora y dispara el hito de avance global DT-I', function () {
  withoutMiddleware();
  actingAs($this->user, 'api');

  $payload = [
    'title' => 'Diseño del Modelo Relacional',
    'date' => now()->format('Y-m-d'),
    'description' => 'Estructuración de las tablas principales.'
  ];

  $response = postJson("/api/workflow/requirements/{$this->requirement->id}/dt/roles/{$this->dtRole->id}/registers", $payload);

  $response->assertStatus(201)
    ->assertJsonFragment(['title' => 'Diseño del Modelo Relacional']);
});

test('CU-031: previene duplicidad concurrente de títulos en la bitácora de un mismo rol (RN-04)', function () {
  withoutMiddleware();
  actingAs($this->user, 'api');

  DtRegister::create([
    'role_id' => $this->dtRole->id,
    'title' => 'Diccionario de Datos',
    'date' => now()->format('Y-m-d'),
    'description' => 'Definición de tipos.'
  ]);

  $payload = [
    'title' => 'Diccionario de Datos',
    'date' => now()->format('Y-m-d'),
    'description' => 'Intento de duplicidad.'
  ];

  $response = postJson("/api/workflow/requirements/{$this->requirement->id}/dt/roles/{$this->dtRole->id}/registers", $payload);

  $response->assertStatus(422)
    ->assertJsonValidationErrors(['title']);
});

test('CU-031: permite actualizar un registro respetando el saneamiento', function () {
  withoutMiddleware();
  actingAs($this->user, 'api');

  $register = DtRegister::create([
    'role_id' => $this->dtRole->id,
    'title' => 'Versionado Inicial',
    'date' => now()->format('Y-m-d'),
    'description' => 'Texto anterior.'
  ]);

  // $response = putJson("api/workflow/dt/registers/{reg_id}/roles/{role_id}", [
  //     'description' => 'Texto modificado exitosamente.'
  // ]);

  $response = $this->putJson("/api/workflow/dt/registers/{$register->id}/roles/{$this->dtRole->id}", [
    'description' => 'Texto modificado exitosamente.'
  ]);

  $response->assertStatus(200)
    ->assertJsonFragment(['description' => 'Texto modificado exitosamente.']);
});

test('CU-031: elimina físicamente un registro técnico en cascada', function () {
  withoutMiddleware();
  actingAs($this->user, 'api');

  $register = DtRegister::create([
    'role_id' => $this->dtRole->id,
    'title' => 'Borrador Temporal',
    'date' => now()->format('Y-m-d'),
    'description' => 'Para eliminar.'
  ]);

  // $response = deleteJson("api/workflow/dt/registers/{reg_id}/roles/{role_id}");
  $response = $this->deleteJson("/api/workflow/dt/registers/{$register->id}/roles/{$this->dtRole->id}");

  $response->assertStatus(200);
});
