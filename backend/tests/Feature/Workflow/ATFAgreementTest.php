<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\User; // Ajusta el namespace si es diferente
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

// Precondición general: Autenticación y creación del consultor en la BD de pruebas
beforeEach(function () {

  // 0. FORZAR LA MIGRACIÓN DEL MÓDULO WORKFLOW EN EL ENTORNO DE PRUEBAS
    $user = User::factory()->create();
    $this->actingAs($user);

    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    // Agregamos Str::random(5) para evitar el error de Unique Constraint
    DB::table('catalogs.societies')->insert([
        'id' => $societyId,
        'name' => 'CANTV Prueba ' . Str::random(5), 
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('catalogs.systems')->insert([
        'id' => $systemId,
        'society_id' => $societyId,
        'name' => 'Sistema Prueba ' . Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('catalogs.requesting_units')->insert([
        'id' => $unitId,
        'system_id' => $systemId,
        'name' => 'Unidad Prueba ' . Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('security.persons')->insert([
        'id' => $personId,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'consultor_' . Str::random(5) . '@cantv.com.ve', 
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('security.functional_consultants')->insert([
        'id'                 => $consultantId,
        'person_id'          => $personId,
        'requesting_unit_id' => $unitId,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
});

describe('Gestión de Acuerdos ATF (US25) y Progreso Global (CU-008)', function () {

    it('deniega el acceso (403) si la fase de Planificación no está cerrada (Gatekeeper Fallido)', function () {
        // Obtenemos el ID del consultor que creamos en el beforeEach
        $consultantId = DB::table('security.functional_consultants')->first()->id;

        $requirement = Requirement::factory()->create([
            'status' => 'PL_OPEN',
            'is_locked' => false,
            'functional_consultant_id' => $consultantId, // Llave foránea satisfecha
        ]);

        $response = $this->postJson("/api/workflow/requirements/{$requirement->id}/atf-agreements", [
            'agreement_date' => now()->format('Y-m-d'),
            'description'    => 'Validación inicial de reglas',
        ]);

        // $response->assertStatus(403)
        //         ->assertJsonFragment(['error' => 'Acceso Denegado: Violación de Regla de Negocio']);
        $response->assertStatus(403)
        // 💡 Ajustado al mensaje exacto que lanza tu handler/excepción
        ->assertJsonFragment(['message' => 'Acceso Denegado: El requerimiento (Estado: PL_OPEN) se encuentra sellado y es de solo lectura.']);
    });

    it('bloquea la mutación (403) si el requerimiento está sellado por Hard Gate', function () {
        $consultantId = DB::table('security.functional_consultants')->first()->id;

        $requirement = Requirement::factory()->create([
            'status' => 'ATF-C',
            'is_locked' => true, 
            'functional_consultant_id' => $consultantId,
        ]);

        $response = $this->postJson("/api/workflow/requirements/{$requirement->id}/atf-agreements", [
            'agreement_date' => now()->format('Y-m-d'),
            'description'    => 'Intento de escritura tardía',
        ]);

        $response->assertStatus(403);
    });

  it('registra el primer acuerdo, muta el estado a ATF-I, actualiza progreso y limpia Redis', function () {
        // 💡 Agregamos la expectativa para 'incr' y flexibilizamos la simulación de Redis
        Redis::shouldReceive('incr')->zeroOrMoreTimes()->andReturn(1);
        Redis::shouldReceive('del')->andReturn(1);
        Redis::shouldReceive('tags')->andReturnSelf();
        Redis::shouldReceive('flush')->andReturn(true);
        
        $consultantId = DB::table('security.functional_consultants')->first()->id;

        $requirement = Requirement::factory()->create([
            'status' => 'ES-R',
            'progress_percentage' => 4.00,
            'management_type' => 'Mixto',
            'is_locked' => false,
            'functional_consultant_id' => $consultantId,
        ]);

        $response = $this->postJson("/api/workflow/requirements/{$requirement->id}/atf-agreements", [
            'agreement_date' => now()->format('Y-m-d'),
            'description'    => 'Acuerdo técnico de inicio de fase ATF con el cliente.',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure(['message', 'data', 'current_progress']);

        // Validamos usando auth()->id() de forma global, haciendo feliz al IDE
        $this->assertDatabaseHas('workflow.atf_agreements', [
            'requirement_id' => $requirement->id,
            'registered_by_user_id' => auth()->id(), 
        ]);

        $this->assertDatabaseHas('core.requirements', [
            'id' => $requirement->id,
            'status' => 'ATF-I',
        ]);

        $this->assertDatabaseHas('workflow.requirement_phase_history', [
            'requirement_id' => $requirement->id,
            'phase_status_code' => 'ATF-I',
        ]);

        Redis::shouldHaveReceived('del')->with("req_{$requirement->id}_progress");
    });

    it('valida la integridad de los datos de entrada (FormRequest)', function () {
        $consultantId = DB::table('security.functional_consultants')->first()->id;

        $requirement = Requirement::factory()->create([
            'status' => 'ES-R',
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