<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\PapRole;
use App\Domains\Workflow\Models\AuRole;
use App\Domains\Workflow\Models\AuTicket;
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
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'test_' . Str::random(5) . '@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $consultantId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);

    // 2. Requerimiento en fase PAP-C (Predecesora ESTRICTA requerida para AU)
    $this->requirement = Requirement::factory()->create([
        'status' => 'PAP-C', 
        'functional_consultant_id' => $consultantId
    ]);

    $this->requirementRole = RequirementRole::factory()->create([
        'requirement_id' => $this->requirement->id,
        'role_name' => 'Analista de Sistemas'
    ]);
    
    // 3. Historial necesario para pasar los Hard Gates Globales
    DB::table('workflow.requirement_phase_history')->insert([
        [
            'id' => Str::uuid()->toString(), 
            'requirement_id' => $this->requirement->id, 
            'phase_status_code' => 'PAP-C', 
            'executed_by_user_id' => $this->user->id,
            'transitioned_at' => now(),
            'created_at' => now(), 
            'updated_at' => now()
        ]
    ]);
});

test('CU-070: inicializa roles en AU migrando desde PAP de forma idempotente', function () {
    withoutMiddleware(); 
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Un rol en PAP en estado IN_PRODUCTION
    PapRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'IN_PRODUCTION',
        'created_by' => $this->user->id,
    ]);

    $response = getJson("/api/workflow/au/requirements/{$this->requirement->id}/roles-init");
    
    $response->assertStatus(200)->assertJsonStructure(['requirement_id', 'roles']);

    // Verificamos migración exitosa a AU
    $this->assertDatabaseHas('workflow.au_roles', [
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_AU'
    ]);

    // Prueba de Idempotencia
    getJson("/api/workflow/au/requirements/{$this->requirement->id}/roles-init")->assertStatus(200);
    expect(AuRole::where('requirement_id', $this->requirement->id)->count())->toBe(1);
});

test('CU-071: registra un ticket CSAL de asignación, asocia roles y avanza a AU-I', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Rol inicializado en AU
    $auRole = AuRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_AU',
        'created_by' => $this->user->id
    ]);

    $pdfFile = UploadedFile::fake()->create('ticket_csal_base.pdf', 100, 'application/pdf');
    $planillaPdf = UploadedFile::fake()->create('planilla_individual.pdf', 100, 'application/pdf');
    
    $uniqueTicketNumber = 'TK-AU-' . Str::upper(Str::random(5));

    $response = postJson("/api/workflow/au/requirements/{$this->requirement->id}/tickets", [
        'ticket_number' => $uniqueTicketNumber, 
        'request_date'  => now()->format('Y-m-d'), 
        'file'          => $pdfFile,
        'planillas'     => [
            $auRole->id => $planillaPdf 
        ],
        'role_ids'      => [$auRole->id]
    ]);

    $response->assertStatus(201);

    // Verificamos el ticket y la vinculación
    $this->assertDatabaseHas('workflow.au_tickets', ['ticket_number' => $uniqueTicketNumber]);
    $this->assertDatabaseHas('workflow.au_roles', [
        'id' => $auRole->id,
        'status' => 'IN_PROGRESS' 
    ]);
});

test('CU-072: registra dictamen de asignación y bifurca estado de roles a ASSIGNED', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $ticket = AuTicket::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'ticket_number' => 'TK-AU-' . Str::random(4), 
        'request_date' => now()->subDays(1)->format('Y-m-d'), 
        'file_path' => 'fake/path_ticket.pdf',
        'status' => 'TKT_IN_PROGRESS', 
        'created_by' => $this->user->id
    ]);

    $auRole = AuRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'ticket_id' => $ticket->id,
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id
    ]);

    $pdfResult = UploadedFile::fake()->create('acta_asignacion.pdf', 100, 'application/pdf');
    
    $evaluations = [
        ['id' => $auRole->id, 'is_approved' => true, 'fail_reason' => null]
    ];

    $response = postJson("/api/workflow/au/tickets/{$ticket->id}/results", [
        'result_file'    => $pdfResult, 
        'reception_date' => now()->format('Y-m-d'),
        'evaluations'    => $evaluations
    ]);

    $response->assertStatus(200);

    // El rol debe estar asignado y el ticket cerrado
    $this->assertDatabaseHas('workflow.au_roles', ['id' => $auRole->id, 'status' => 'ASSIGNED']);
    
    
    $this->assertDatabaseHas('workflow.au_tickets', ['id' => $ticket->id, 'status' => 'TKT_CLOSED']); 
});

test('CU-073: Hard Gate deniega el cierre global si existen roles sin acceso asignado', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    AuRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_AU',
        'created_by' => $this->user->id
    ]);

    $response = postJson("/api/workflow/au/requirements/{$this->requirement->id}/finalize-au");

    $response->assertStatus(422)->assertSee('ASSIGNED');
});