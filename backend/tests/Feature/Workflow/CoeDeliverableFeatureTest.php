<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\Deliverable; // El maestro de ATF
use App\Domains\Workflow\Models\CoeDeliverable;
use App\Domains\Workflow\Models\CoeActivity;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    
    $this->user = User::factory()->create([
    'email' => 'user_' . Str::random(8) . '@cantv.com.ve',
    'roles' => ['Admin'],
]);
    

    // 1. Construcción del Grafo Jerárquico Base
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

    // 2. Requerimiento en estado óptimo para iniciar COE
    $this->requirement = Requirement::factory()->create([
        'status' => 'COR-C', // Asumimos que roles ya cerró, listos para entregables (o puede ser paralelo)
        'functional_consultant_id' => $consultantId
    ]);

    // 3. Creación del Entregable Maestro (Nacido en ATF)
    $this->masterDeliverable = Deliverable::create([
        'requirement_id' => $this->requirement->id,
        'name' => 'Manual de Arquitectura Cloud',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);
});

test('CU-071: inicializa y sincroniza entregables en COE migrando desde la tabla maestra (ATF)', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // Ejecuta la sincronización (INSERT ... SELECT)
    $response = getJson("/api/workflow/requirements/{$this->requirement->id}/coe/deliverables-init");

    $response->assertStatus(200)
            ->assertJsonStructure([
                'requirement_id',
                'roles_list' => [ // Usamos roles_list por compatibilidad polimórfica en Angular
                    '*' => ['id', 'req_id', 'deliverable_id', 'status', 'master_deliverable' => ['name']]
                ]
            ]);

    // Valida la inserción física
    $this->assertDatabaseHas('workflow.coe_deliverables', [
        'req_id' => $this->requirement->id,
        'deliverable_id' => $this->masterDeliverable->id,
        'status' => 'IN_PROGRESS'
    ]);

    // Prueba de idempotencia: Una segunda llamada no debe duplicar registros
    $responseIdempotente = getJson("/api/workflow/requirements/{$this->requirement->id}/coe/deliverables-init");
    $responseIdempotente->assertStatus(200);
    
    $count = CoeDeliverable::where('req_id', $this->requirement->id)->count();
    expect($count)->toBe(1);
});

test('CU-075: deniega el cierre de un entregable en COE si no posee actividades en su bitácora', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $coeDeliverable = CoeDeliverable::create([
        'req_id' => $this->requirement->id,
        'deliverable_id' => $this->masterDeliverable->id,
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    $response = patchJson("/api/workflow/coe/deliverables/{$coeDeliverable->id}/status", [
        'new_status' => 'CLOSED'
    ]);

    $response->assertStatus(422); // Unprocessable Entity por regla de negocio
});

test('CU-075: permite cerrar un entregable en COE si posee evidencia documental (actividades)', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $coeDeliverable = CoeDeliverable::create([
        'req_id' => $this->requirement->id,
        'deliverable_id' => $this->masterDeliverable->id,
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    CoeActivity::create([
        'coe_deliverable_id' => $coeDeliverable->id,
        'title' => 'Redacción del Capítulo 1',
        'date' => now()->format('Y-m-d'),
        'description' => 'Definición de topología de red.',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id
    ]);

    $response = patchJson("/api/workflow/coe/deliverables/{$coeDeliverable->id}/status", [
        'new_status' => 'CLOSED'
    ]);

    $response->assertStatus(200)
            ->assertJsonFragment(['new_status' => 'CLOSED']);
});