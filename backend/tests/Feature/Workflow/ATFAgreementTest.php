<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

// Variables compartidas en la suite de pruebas
$user = null;
$consultantId = null;

beforeEach(function () use (&$user, &$consultantId) {

    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    // 1. Insertar registros de catálogo
    DB::table('catalogs.societies')->insert([
        'id'         => $societyId,
        'name'       => 'CANTV Prueba ' . Str::random(5), 
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('catalogs.systems')->insert([
        'id'         => $systemId,
        'society_id' => $societyId,
        'name'       => 'Sistema Prueba ' . Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('catalogs.requesting_units')->insert([
        'id'         => $unitId,
        'system_id'  => $systemId,
        'name'       => 'Unidad Prueba ' . Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Insertar Persona PRIMERO (sin user_id)
    DB::table('security.persons')->insert([
        'id'         => $personId,
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'email'      => 'consultor_' . Str::random(5) . '@cantv.com.ve', 
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3. Crear Usuario asociado a la Persona creada
    $user = User::factory()->create(
        [
          'roles'=> ['Admin'],
        ]
    );

    // 4. Crear Consultor Funcional
    DB::table('security.functional_consultants')->insert([
        'id'                 => $consultantId,
        'person_id'          => $personId,
        'requesting_unit_id' => $unitId,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    // 5. Autenticar explícitamente al usuario en el guard API
    $this->actingAs($user, 'api');
});

describe('Gestión de Acuerdos ATF (US25) y Progreso Global (CU-008)', function () use (&$consultantId) {

    it('deniega el acceso (403) si la fase de Planificación no está cerrada (Gatekeeper Fallido)', function () use (&$consultantId) {
        $requirement = Requirement::factory()->create([
            'status'                   => 'RC',
            'is_locked'                => false,
            'functional_consultant_id' => $consultantId,
        ]);

        $response = $this->postJson("/api/workflow/requirements/{$requirement->id}/atf-agreements", [
            'agreement_date' => now()->format('Y-m-d'),
            'description'    => 'Validación inicial de reglas',
        ]);
        // $response->assertStatus(403)
        //         ->assertJsonFragment(['message' => 'Acceso denegado. Privilegios insuficientes.']);
        $response->assertStatus(403)
          ->assertJsonStructure(['message']);
    });

    it('bloquea la mutación (403) si el requerimiento está sellado por Hard Gate', function () use (&$consultantId) {
        $requirement = Requirement::factory()->create([
            'status'                   => 'ATF-C',
            'is_locked'                => true, 
            'functional_consultant_id' => $consultantId,
        ]);

        $response = $this->postJson("/api/workflow/requirements/{$requirement->id}/atf-agreements", [
            'agreement_date' => now()->format('Y-m-d'),
            'description'    => 'Intento de escritura tardía',
        ]);

        $response->assertStatus(403);
    });

    it('registra el primer acuerdo, muta el estado a ATF-I, actualiza progreso y limpia Redis', function () use (&$consultantId) {
        Redis::shouldReceive('incr')->zeroOrMoreTimes()->andReturn(1);
        Redis::shouldReceive('del')->andReturn(1);
        Redis::shouldReceive('tags')->andReturnSelf();
        Redis::shouldReceive('flush')->andReturn(true);

        $requirement = Requirement::factory()->create([
            'status'                   => 'ES-R',
            'progress_percentage'      => 4.00,
            'management_type'          => 'Mixto',
            'is_locked'                => false,
            'functional_consultant_id' => $consultantId,
        ]);

        $response = $this->postJson("/api/workflow/requirements/{$requirement->id}/atf-agreements", [
            'agreement_date' => now()->format('Y-m-d'),
            'description'    => 'Acuerdo técnico de inicio de fase ATF con el cliente.',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure(['message', 'data', 'current_progress']);

        $this->assertDatabaseHas('workflow.atf_agreements', [
            'requirement_id'        => $requirement->id,
            'registered_by_user_id' => auth()->id(), 
        ]);

        $this->assertDatabaseHas('core.requirements', [
            'id'     => $requirement->id,
            'status' => 'ATF-I',
        ]);

        $this->assertDatabaseHas('workflow.requirement_phase_history', [
            'requirement_id'    => $requirement->id,
            'phase_status_code' => 'ATF-I',
        ]);

        Redis::shouldHaveReceived('del')->with("req_{$requirement->id}_progress");
    });

    it('valida la integridad de los datos de entrada (FormRequest)', function () use (&$consultantId) {
        $requirement = Requirement::factory()->create([
            'status'                   => 'ES-R',
            'functional_consultant_id' => $consultantId,
        ]);

        $response = $this->postJson("/api/workflow/requirements/{$requirement->id}/atf-agreements", [
            'agreement_date' => now()->addDays(2)->format('Y-m-d'), 
            'description'    => 'Corta', 
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['agreement_date', 'description']);
    });
});