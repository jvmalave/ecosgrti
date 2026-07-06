<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\AtfAgreement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Security\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);

$consultantId = '';

beforeEach(function () use (&$consultantId) {
    config(['logging.default' => 'stderr']);
    config(['logging.channels.audit' => ['driver' => 'null']]);

    // Setup organizacional
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

    // Asegurar esquema y tabla de auditoría
    DB::statement('CREATE SCHEMA IF NOT EXISTS audit');
    DB::statement('CREATE TABLE IF NOT EXISTS audit.workflow_logs (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        action VARCHAR(255),
        user_id UUID,
        requirement_id UUID,
        timestamp TIMESTAMP
    )');
});

// Helper para autenticación en tests
function actingAsUser() {
    return test()->actingAs(User::factory()->create(), 'api');
}

it('rechaza el cierre de fase si no se cumple el quórum mínimo (422)', function () use (&$consultantId) {
    $req = Requirement::factory()->create(['functional_consultant_id' => $consultantId, 'management_type' => 'Mixto']);

    $response = actingAsUser()->postJson("/api/workflow/requirements/{$req->id}/close-atf");

    $response->assertStatus(422)
            ->assertJsonValidationErrors(['quorum']);
});

it('permite el cierre de fase si se cumple el quórum (200)', function () use (&$consultantId) {
    $user = User::factory()->create(); // Creamos el usuario
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'management_type' => 'Mixto'
    ]);
    
    // Pasamos el ID del usuario para cumplir la FK
    AtfAgreement::factory()->create([
        'requirement_id' => $req->id,
        'registered_by_user_id' => $user->id 
    ]);
    
    RequirementRole::factory()->create(['requirement_id' => $req->id]);

    $response = $this->actingAs($user, 'api') // Usamos este usuario para actuar
                    ->postJson("/api/workflow/requirements/{$req->id}/close-atf");

    $response->assertStatus(200)
            ->assertJsonPath('status', 'ATF_COMPLETED');
});


it('rechaza cualquier intento de cierre si el requerimiento ya esta bloqueado', function () use (&$consultantId) {
    $req = Requirement::factory()->create([
        'functional_consultant_id' => $consultantId,
        'status' => 'CLOSED',
        'is_locked' => true
    ]);

    $response = actingAsUser()->postJson("/api/workflow/requirements/{$req->id}/close-atf");

    // Esperamos un error 422 porque la validación en el servicio detecta que ya está bloqueado
    $response->assertStatus(422);
});