<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\CeeDeliverable;
use App\Domains\Workflow\Models\CeeTicket;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    Storage::fake('local'); 
    
    $this->user = User::factory()->create();

    // 1. Grafo Jerárquico Básico
    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV Prueba ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'Sistema Prueba ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'system_id' => $systemId, 'name' => 'Unidad Prueba ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'test_' . Str::random(5) . '@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $consultantId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);

    // 2. Requerimiento en fase COE-C (Predecesora requerida para CEE)
    $this->requirement = Requirement::factory()->create([
        'status' => 'COE-C', 
        'functional_consultant_id' => $consultantId
    ]);

    // 3. Entregable Maestro
    $this->deliverableId = Str::uuid()->toString();
    DB::table('workflow.deliverables')->insert([
        'id' => $this->deliverableId,
        'requirement_id' => $this->requirement->id, 
        'name' => 'Manual de Usuario',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // 4. Historial necesario para pasar los Hard Gates Globales
    DB::table('workflow.requirement_phase_history')->insert([
        [
            'id' => Str::uuid()->toString(), 
            'requirement_id' => $this->requirement->id, 
            'phase_status_code' => 'COE-C', 
            'executed_by_user_id' => $this->user->id,
            'transitioned_at' => now(),
            'created_at' => now(), 
            'updated_at' => now()
        ]
    ]);
});

test('CU-077: inicializa entregables en CEE migrando desde COE de forma idempotente', function () {
    withoutMiddleware(); 
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Un entregable en COE en estado CLOSED
    DB::table('workflow.coe_deliverables')->insert([
        'id' => Str::uuid()->toString(),
        'req_id' => $this->requirement->id,
        'deliverable_id' => $this->deliverableId,
        'status' => 'CLOSED',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now()
    ]);

    $response = getJson("/api/workflow/cee/requirements/{$this->requirement->id}/deliverables-init");
    
    $response->assertStatus(200)->assertJsonStructure(['requirement_id', 'deliverables']);

    // Verificamos migración exitosa
    $this->assertDatabaseHas('workflow.cee_deliverables', [
        'requirement_id' => $this->requirement->id,
        'deliverable_id' => $this->deliverableId,
        'status' => 'PENDING_CERTIFICATION'
    ]);

    // Prueba de Idempotencia
    getJson("/api/workflow/cee/requirements/{$this->requirement->id}/deliverables-init")->assertStatus(200);
    expect(CeeDeliverable::where('requirement_id', $this->requirement->id)->count())->toBe(1);
});

test('CU-079: registra un ticket de certificación autogenerado, asocia entregables y avanza a CEE-I', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Entregable inicializado en CEE
    $ceeDeliverable = CeeDeliverable::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'deliverable_id' => $this->deliverableId,
        'status' => 'PENDING_CERTIFICATION',
        'created_by' => $this->user->id
    ]);

    $pdfFile = UploadedFile::fake()->create('solicitud_funcional.pdf', 100, 'application/pdf');

    // RN-CEE: Enviamos el request SIN ticket_number porque el sistema lo genera
    $response = postJson("/api/workflow/cee/requirements/{$this->requirement->id}/tickets", [
        'request_date'    => now()->format('Y-m-d'),
        'file'            => $pdfFile,
        'deliverable_ids' => [$ceeDeliverable->id]
    ]);

    $response->assertStatus(201);

    // Verificamos que el ticket se generó con el prefijo CEE-
    $ticketCount = CeeTicket::where('requirement_id', $this->requirement->id)
        ->where('ticket_number', 'LIKE', 'CEE-%')
        ->count();
    expect($ticketCount)->toBe(1);

    // Verificamos vinculación en cascada
    $this->assertDatabaseHas('workflow.cee_deliverables', [
        'id' => $ceeDeliverable->id,
        'status' => 'IN_PROGRESS' 
    ]);
    
    // Verificamos el disparo automático de la fase maestra a CEE-I
    $this->assertDatabaseHas('core.requirements', [
        'id' => $this->requirement->id,
        'status' => 'CEE-I'
    ]);
});

test('CU-080: registra resultado de certificación y bifurca estado de entregables', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $ticket = CeeTicket::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'ticket_number' => 'CEE-' . Str::random(6),
        'request_date' => now()->subDays(2)->format('Y-m-d'),
        'file_path' => 'fake/path.pdf',
        'status' => 'TKT_IN_PROGRESS',
        'created_by' => $this->user->id
    ]);

    $ceeDeliverable = CeeDeliverable::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'deliverable_id' => $this->deliverableId,
        'ticket_id' => $ticket->id,
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id
    ]);

    $pdfResult = UploadedFile::fake()->create('acta_aprobacion.pdf', 100, 'application/pdf');
    
    $evaluations = json_encode([
        ['id' => $ceeDeliverable->id, 'is_approved' => true, 'rejection_reason' => null]
    ]);

    $response = postJson("/api/workflow/cee/tickets/{$ticket->id}/results", [
        'file' => $pdfResult,
        'evaluations' => $evaluations
    ]);

    $response->assertStatus(200);

    // El entregable debe estar certificado y el ticket cerrado con categoría TOTAL
    $this->assertDatabaseHas('workflow.cee_deliverables', ['id' => $ceeDeliverable->id, 'status' => 'CERTIFIED']);
    $this->assertDatabaseHas('workflow.cee_tickets', ['id' => $ticket->id, 'status' => 'TKT_CLOSED', 'result_category' => 'TOTAL']);
});

test('CU-081: Hard Gate deniega el cierre global si existen entregables sin certificar', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // Creamos un entregable atascado en PENDING
    CeeDeliverable::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'deliverable_id' => $this->deliverableId,
        'status' => 'PENDING_CERTIFICATION',
        'created_by' => $this->user->id
    ]);

    $response = patchJson("/api/workflow/cee/requirements/{$this->requirement->id}/close");

    $response->assertStatus(422)->assertSee('CERTIFIED');
});