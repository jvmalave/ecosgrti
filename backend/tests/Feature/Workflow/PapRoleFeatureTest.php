<?php

declare(strict_types=1);

use App\Domains\Security\Models\User;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\CerRole;
use App\Domains\Workflow\Models\PapRole;
use App\Domains\Workflow\Models\PapOrder;
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

    // 2. Requerimiento en fase CER-C (Predecesora ESTRICTA requerida para PAP)
    $this->requirement = Requirement::factory()->create([
        'status' => 'CER-C', 
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
            'phase_status_code' => 'CER-C', 
            'executed_by_user_id' => $this->user->id,
            'transitioned_at' => now(),
            'created_at' => now(), 
            'updated_at' => now()
        ]
    ]);
});

test('CU-054: inicializa roles en PAP migrando desde CER de forma idempotente', function () {
    withoutMiddleware(); 
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Un rol en CER en estado CERTIFIED
    CerRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'CERTIFIED',
        'created_by' => $this->user->id,
    ]);

    $response = getJson("/api/workflow/pap/requirements/{$this->requirement->id}/roles-init");
    
    $response->assertStatus(200)->assertJsonStructure(['requirement_id', 'roles']);

    // Verificamos migración exitosa a PAP
    $this->assertDatabaseHas('workflow.pap_roles', [
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_PAP'
    ]);

    // Prueba de Idempotencia
    getJson("/api/workflow/pap/requirements/{$this->requirement->id}/roles-init")->assertStatus(200);
    expect(PapRole::where('requirement_id', $this->requirement->id)->count())->toBe(1);
});

test('CU-055: registra una orden de transporte, asocia roles y avanza a PAP-I', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // PRECONDICIÓN: Rol inicializado en PAP
    $papRole = PapRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_PAP',
        'created_by' => $this->user->id
    ]);

    $pdfFile = UploadedFile::fake()->create('orden_base.pdf', 100, 'application/pdf');
    
    // Hacemos dinámico el número de orden para evitar la validación "unique"
    $uniqueOrderNumber = 'ORD-2026-' . Str::upper(Str::random(5));

    $response = postJson("/api/workflow/pap/requirements/{$this->requirement->id}/orders", [
        'order_number' => $uniqueOrderNumber, 
        'date'         => now()->format('Y-m-d'),
        'file'         => $pdfFile,
        'role_ids'     => [$papRole->id]
    ]);

    $response->assertStatus(201);

    // Verificamos la orden de transporte y vinculación con la variable dinámica
    $this->assertDatabaseHas('workflow.pap_orders', ['order_number' => $uniqueOrderNumber]);
    $this->assertDatabaseHas('workflow.pap_roles', [
        'id' => $papRole->id,
        'status' => 'IN_PROGRESS' 
    ]);
    
    // Verificamos el disparo automático de la fase maestra
    $this->assertDatabaseHas('core.requirements', [
        'id' => $this->requirement->id,
        'status' => 'PAP-I'
    ]);
});

test('CU-056: registra dictamen de despliegue y bifurca estado de roles (Éxito)', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    $order = PapOrder::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'order_number' => 'ORD-2026-' . Str::random(4), 
        'date' => now()->subDays(2)->format('Y-m-d'),
        'file_path' => 'fake/path.pdf',
        'status' => 'ORD_IN_PROGRESS',
        'created_by' => $this->user->id
    ]);

    $papRole = PapRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'order_id' => $order->id,
        'status' => 'IN_PROGRESS',
        'created_by' => $this->user->id
    ]);

    $pdfResult = UploadedFile::fake()->create('acta_dictamen.pdf', 100, 'application/pdf');
    
    // Evaluaciones
    $evaluations = [
        ['id' => $papRole->id, 'is_approved' => true, 'fail_reason' => null]
    ];

    $response = postJson("/api/workflow/pap/orders/{$order->id}/results", [
        'file' => $pdfResult,
        'evaluations' => $evaluations
    ]);

    $response->assertStatus(200);

    // El rol debe estar en producción y la orden cerrada
    $this->assertDatabaseHas('workflow.pap_roles', ['id' => $papRole->id, 'status' => 'IN_PRODUCTION']);
    $this->assertDatabaseHas('workflow.pap_orders', ['id' => $order->id, 'status' => 'ORD_CLOSED']);
});

test('CU-057: Hard Gate deniega el cierre global si existen roles sin pasar a producción', function () {
    withoutMiddleware();
    actingAs($this->user, 'api');

    // Recreamos el escenario que resolvimos a las 4 AM: un rol atascado
    PapRole::create([
        'id' => Str::uuid()->toString(),
        'requirement_id' => $this->requirement->id,
        'requirement_role_id' => $this->requirementRole->id,
        'status' => 'PENDING_PAP',
        'created_by' => $this->user->id
    ]);

    $response = patchJson("/api/workflow/pap/requirements/{$this->requirement->id}/close-phase");

    // Gracias al método getCompletedStatusCode() que sobrescribiste, sabemos que buscará IN_PRODUCTION
    $response->assertStatus(422)->assertSee('IN_PRODUCTION');
});
