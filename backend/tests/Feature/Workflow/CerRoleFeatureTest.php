<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\PiRole;
use App\Domains\Workflow\Models\CerRole;
use App\Domains\Workflow\Models\CerTicket;
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

    // 1. Grafo Jerárquico Básico (Agregamos Str::random para evitar colisiones Unique)
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

    // 2. Requerimiento en fase PI-C (Predecesora requerida para CER)
    $this->requirement = Requirement::factory()->create([
        'status' => 'PI-C', 
        'functional_consultant_id' => $consultantId
    ]);

    $this->requirementRole = RequirementRole::factory()->create([
        'requirement_id' => $this->requirement->id,
        'role_name' => 'Administrador de Base de Datos'
    ]);
    
    // 3. Historial necesario para pasar los Hard Gates Globales
    DB::table('workflow.requirement_phase_history')->insert([
        [
            'id' => Str::uuid()->toString(), 
            'requirement_id' => $this->requirement->id, 
            'phase_status_code' => 'PI-C', 
            'executed_by_user_id' => $this->user->id,
            'transitioned_at' => now(),
            'created_at' => now(), 
            'updated_at' => now()
        ]
    ]);
});

test('CU-047: inicializa roles en CER migrando desde PI de forma idempotente', function () {
    withoutMiddleware(); 
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Un rol en PI en estado CLOSED
    PiRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'CLOSED',
        'is_approved' => true,
        'created_by' => $this->user->id,
    ]);

    $response = getJson("/api/workflow/cer/requirements/{$this->requirement->id}/roles-init");
    
    $response->assertStatus(200)->assertJsonStructure(['requirement_id', 'roles']);

    // Verificamos migración exitosa
    $this->assertDatabaseHas('workflow.cer_roles', [
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_CERTIFICATION'
    ]);

    // Prueba de Idempotencia
    getJson("/api/workflow/cer/requirements/{$this->requirement->id}/roles-init")->assertStatus(200);
    expect(CerRole::where('requirement_id', $this->requirement->id)->count())->toBe(1);
});

test('CU-049: registra un ticket de certificación, asocia roles y avanza a CER-I', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Rol inicializado en CER
    $cerRole = CerRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_CERTIFICATION',
        'created_by' => $this->user->id
    ]);

    $pdfFile = UploadedFile::fake()->create('solicitud_csal.pdf', 100, 'application/pdf');

    $response = postJson("/api/workflow/cer/requirements/{$this->requirement->id}/tickets", [
        'ticket_number' => 'CSAL-2026-999', // RN-CER: Digitado manualmente
        'request_date'  => now()->format('Y-m-d'),
        'file'          => $pdfFile,
        'role_ids'      => [$cerRole->id]
    ]);

    $response->assertStatus(201);

    // Verificamos ticket y vinculación en cascada
    $this->assertDatabaseHas('workflow.cer_tickets', ['ticket_number' => 'CSAL-2026-999']);
    $this->assertDatabaseHas('workflow.cer_roles', [
        'id' => $cerRole->id,
        'status' => 'IN_PROGRESS' // El rol pasó a En Proceso
    ]);
    
    // Verificamos el disparo automático de la fase maestra
    $this->assertDatabaseHas('core.requirements', [
        'id' => $this->requirement->id,
        'status' => 'CER-I'
    ]);
});

test('CU-050: registra resultado de certificación y bifurca estado de roles', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $ticket = CerTicket::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'ticket_number' => 'CSAL-2026-' . Str::random(4), 
        'request_date' => now()->subDays(2)->format('Y-m-d'),
        'file_path' => 'fake/path.pdf',
        'status' => 'TKT_IN_PROGRESS',
        'created_by' => $this->user->id
    ]);

    $cerRole = CerRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'ticket_id' => $ticket->id,
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id
    ]);

    $pdfResult = UploadedFile::fake()->create('dictamen_csal.pdf', 100, 'application/pdf');
    
    // Evaluaciones en formato JSON stringificado (como lo enviará Angular)
    $evaluations = json_encode([
        ['id' => $cerRole->id, 'is_approved' => true, 'rejection_reason' => null]
    ]);

    $response = postJson("/api/workflow/cer/tickets/{$ticket->id}/results", [
        'file' => $pdfResult,
        'evaluations' => $evaluations
    ]);

    $response->assertStatus(200);

    // El rol debe estar certificado y el ticket cerrado con categoría TOTAL
    $this->assertDatabaseHas('workflow.cer_roles', ['id' => $cerRole->id, 'status' => 'CERTIFIED']);
    $this->assertDatabaseHas('workflow.cer_tickets', ['id' => $ticket->id, 'status' => 'TKT_CLOSED', 'result_category' => 'TOTAL']);
});

test('CU-051: Hard Gate deniega el cierre global si existen roles sin certificar', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // Creamos un rol atascado en PENDING
    CerRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_CERTIFICATION',
        'created_by' => $this->user->id
    ]);

    $response = patchJson("/api/workflow/cer/requirements/{$this->requirement->id}/close");

    // El motor abstracto debe devolver 422 por no cumplir con el estado dinámico (CERTIFIED)
    $response->assertStatus(422)->assertSee('CERTIFIED');
});