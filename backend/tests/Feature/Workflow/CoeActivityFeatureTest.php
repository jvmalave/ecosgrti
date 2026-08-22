<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\Deliverable;
use App\Domains\Workflow\Models\CoeDeliverable;
use App\Domains\Workflow\Models\CoeActivity;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    
    $this->user = User::factory()->create();

    // 1. Construcción Básica
    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SGRTI ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'system_id' => $systemId, 'name' => 'IT ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test_' . Str::random(5) . '@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $consultantId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);

    $this->requirement = Requirement::factory()->create([
        'status' => 'ATF-C',
        'functional_consultant_id' => $consultantId
    ]);

    $this->masterDeliverable = Deliverable::create([
        'requirement_id' => $this->requirement->id,
        'name' => 'Manual de Usuario',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    // Entregable ya migrado a la fase COE
    $this->coeDeliverable = CoeDeliverable::create([
        'req_id' => $this->requirement->id,
        'deliverable_id' => $this->masterDeliverable->id,
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);
});

test('CU-073: almacena una actividad documental y dispara el hito de avance global COE-I', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $payload = [
        'title' => 'Levantamiento de Pantallas',
        'date' => now()->format('Y-m-d'),
        'description' => 'Capturas de pantalla del módulo de autenticación integradas en el documento.'
    ];

    $response = postJson("/api/workflow/requirements/{$this->requirement->id}/coe/deliverables/{$this->coeDeliverable->id}/activities", $payload);

    $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Levantamiento de Pantallas']);
            
    // Valida que el hito COE-I se disparó en el requerimiento
    $this->assertDatabaseHas('core.requirements', [
        'id' => $this->requirement->id,
        'status' => 'COE-I'
    ]);
});

test('CU-073/074: previene duplicidad concurrente de títulos en la bitácora de un mismo entregable', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // 1. Creamos el registro inicial simulando el estilo de COR
    CoeActivity::create([
        'coe_deliverable_id' => $this->coeDeliverable->id,
        'title' => 'Script de Base de Datos', // Sin acentos para evitar fallos de collation
        'date' => now()->format('Y-m-d'),
        'description' => 'Definición inicial.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    // 2. Intentamos inyectar el duplicado exacto
    $payload = [
        'title' => 'Script de Base de Datos',
        'date' => now()->format('Y-m-d'),
        'description' => 'Intento de duplicidad en Construcción.'
    ];

    $response = postJson("/api/workflow/requirements/{$this->requirement->id}/coe/deliverables/{$this->coeDeliverable->id}/activities", $payload);

    // 3. Afirmamos que el validador lo detiene con un 422
    $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
});

test('CU-074: permite actualizar una actividad respetando el saneamiento y la validación ignore unique', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $activity = CoeActivity::create([
        'coe_deliverable_id' => $this->coeDeliverable->id,
        'title' => 'Diagrama de Flujo Original',
        'date' => now()->format('Y-m-d'),
        'description' => 'Descripción antigua.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    $response = putJson("/api/workflow/coe/activities/{$activity->id}/deliverables/{$this->coeDeliverable->id}", [
        'title' => 'Diagrama de Flujo Original', // Re-enviamos el mismo título para probar el ignore()
        'date' => now()->format('Y-m-d'),
        'description' => 'Se actualizó la descripción para reflejar los nuevos cambios del cliente.'
    ]);

    $response->assertStatus(200)
            ->assertJsonFragment(['description' => 'Se actualizó la descripción para reflejar los nuevos cambios del cliente.']);
});

test('CU-074: elimina lógicamente (soft delete) una actividad documental', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $activity = CoeActivity::create([
        'coe_deliverable_id' => $this->coeDeliverable->id,
        'title' => 'Borrador Temporal',
        'date' => now()->format('Y-m-d'),
        'description' => 'Para eliminar en pruebas de regresión.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    $response = deleteJson("/api/workflow/coe/activities/{$activity->id}/deliverables/{$this->coeDeliverable->id}");

    $response->assertStatus(200);
    
    // Verifica que el borrado preservó el ID del usuario
    $this->assertDatabaseHas('workflow.coe_activities', [
        'id' => $activity->id,
        'deleted_by' => $this->user->id
    ]);
});