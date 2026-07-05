<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementPhaseHistory;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 1. Declaración de variables de estado fuera de los closures para evitar 'Undefined property'
$consultantId = '';

beforeEach(function () use (&$consultantId) {
    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SGRTI', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'system_id' => $systemId, 'name' => 'CSPE', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'test@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $consultantId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);
    
    Redis::flushall();
});

it('calcula el progreso, lo persiste en Redis y retorna el JSON correcto', function () use (&$consultantId) {
    // 2. Arrange
    $requirement = Requirement::factory()->create([
        'management_type' => 'Mixto',
        'functional_consultant_id' => $consultantId // Ahora usamos la variable local, no $this
    ]);

    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'RC']);
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'ES-R']);

    // 3. Act: Llamada al endpoint
    $response = $this->getJson("/api/workflow/requirements/{$requirement->id}/progress-dashboard");

    // 4. Assert: Usamos una comparación flexible
    $response->assertStatus(200);
    
    // Extraemos el valor del JSON de la respuesta y usamos assertEquals (flexible)
    $progress = $response->json('data.current_progress');
    $this->assertEquals(5.0, $progress); 

    // 5. Assert Redis:
    $cachedData = json_decode(Redis::get("req_{$requirement->id}_progress"), true);
    $this->assertEquals(5.0, $cachedData['current_progress']);
});