<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementPhaseHistory;
use App\Domains\Security\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    
    // Autenticar usuario para la prueba
    $user = User::factory()->create();
    $this->actingAs($user);

    Redis::flushall();
});

it('calcula el progreso, lo persiste en Redis y retorna el JSON correcto', function () use (&$consultantId) {
    // Arrange
    $requirement = Requirement::factory()->create([
        'management_type' => 'Mixto',
        'functional_consultant_id' => $consultantId
    ]);

    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'RC']);
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'ES-R']);

    // Act
    $response = $this->getJson("/api/workflow/requirements/{$requirement->id}/progress-dashboard");

    // Assert
    $response->assertStatus(200);
    
    $progress = $response->json('data.current_progress');
    $this->assertEquals(5.0, $progress); 

    // Assert Redis
    $cachedData = json_decode(Redis::get("req_{$requirement->id}_progress"), true);
    $this->assertEquals(5.0, $cachedData['current_progress']);
});