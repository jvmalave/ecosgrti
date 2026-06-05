<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use PharIo\Manifest\Email;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\withoutMiddleware; // <-- Importamos el helper

// Utilizamos RefreshDatabase para el entorno limpio
uses(RefreshDatabase::class);

// Apagamos los middlewares de ruta (Roles) para concentrarnos 100% en el Core Business
beforeEach(function () {
    withoutMiddleware();
});

/**
 * Función Helper para generar el ecosistema de datos del test.
 *
 * @return array{consultant: User, requirement: Requirement, validPhases: array}
 */
/**
 * Función Helper para generar el ecosistema de datos del test.
 *
 * @return array{consultant: User, requirement: Requirement, validPhases: array}
 */
function getEstimationTestData(): array
{
    // 1. Usuario Base
    $consultant = User::factory()->create();

    // --- CONSTRUCCIÓN DEL GRAFO JERÁRQUICO INVERSO PARA POSTGRESQL ---
    $societyId = (string) \Illuminate\Support\Str::uuid();
    $systemId = (string) \Illuminate\Support\Str::uuid();
    $requestingUnitId = (string) \Illuminate\Support\Str::uuid(); 
    $personId = (string) \Illuminate\Support\Str::uuid(); 
    $functionalConsultantId = (string) \Illuminate\Support\Str::uuid();

    // Nivel 1: Sociedad (Bisabuelo 1)
    \Illuminate\Support\Facades\DB::table('catalogs.societies')->insert([
        'id' => $societyId,
        'name' => 'CANTV',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Nivel 2: Sistema (Bisabuelo 2)
    \Illuminate\Support\Facades\DB::table('catalogs.systems')->insert([
        'id' => $systemId,
        'society_id' => $societyId, // Enlazamos a la Sociedad
        'name' => 'SISTEMA CORE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Nivel 3A: Unidad Solicitante (Abuelo 1)
    \Illuminate\Support\Facades\DB::table('catalogs.requesting_units')->insert([
        'id' => $requestingUnitId,
        'system_id' => $systemId, // Enlazamos al Sistema
        'name' => 'Unidad de Pruebas Automatizadas',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Nivel 3B: Persona (Abuelo 2)
    \Illuminate\Support\Facades\DB::table('security.persons')->insert([
        'id' => $personId,
        'first_name' => 'Consultor',
        'last_name' => 'Prueba',
        'email' => 'consultor.prueba@example.com',
        'phone' => '04121234567',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Nivel 4: Consultor Funcional (Padre)
    \Illuminate\Support\Facades\DB::table('security.functional_consultants')->insert([
        'id' => $functionalConsultantId,
        'person_id' => $personId, 
        'requesting_unit_id' => $requestingUnitId, // Enlazamos a la Unidad
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    // ------------------------------------------------------------------------

    // Nivel 5: Instanciamos el Requerimiento manualmente (El Hijo)
    $requirement = new Requirement();
    $requirement->id = (string) \Illuminate\Support\Str::uuid();
    $requirement->rrti = 'RRTI-' . rand(1000, 9999);
    $requirement->requirement_type = 'NUEVO SISTEMA';
    $requirement->creation_date = Carbon::now()->subDays(2)->toDateString();
    $requirement->description = 'Descripción válida de prueba con más de diez caracteres.';
    
    // --- CAMPOS OBLIGATORIOS ---
    $requirement->management_type = 'DESARROLLO INTERNO'; 
    $requirement->snapshot_society_name = 'CANTV';
    $requirement->snapshot_system_name = 'SISTEMA CORE';
    $requirement->snapshot_unit_name = 'GERENCIA DE TECNOLOGÍA';
    
    // ¡ENLAZAMOS CON EL PADRE!
    $requirement->functional_consultant_id = $functionalConsultantId; 
    $requirement->status = 'PL';
    
    $requirement->is_locked = false;
    $requirement->created_at = Carbon::now()->subDays(2);
    $requirement->save();

    // 6. Generamos el payload válido base para las 6 fases (Camino de Hierro)
    $validPhases = [
        ['phase_name' => 'ATF', 'start_date' => Carbon::now()->toDateString(), 'end_date' => Carbon::now()->addDays(2)->toDateString(), 'estimated_hours' => 16],
        ['phase_name' => 'DISENO', 'start_date' => Carbon::now()->addDays(3)->toDateString(), 'end_date' => Carbon::now()->addDays(5)->toDateString(), 'estimated_hours' => 24],
        ['phase_name' => 'CONSTRUCCION', 'start_date' => Carbon::now()->addDays(6)->toDateString(), 'end_date' => Carbon::now()->addDays(15)->toDateString(), 'estimated_hours' => 80],
        ['phase_name' => 'PRUEBAS', 'start_date' => Carbon::now()->addDays(16)->toDateString(), 'end_date' => Carbon::now()->addDays(20)->toDateString(), 'estimated_hours' => 40],
        ['phase_name' => 'CERTIFICACION', 'start_date' => Carbon::now()->addDays(21)->toDateString(), 'end_date' => Carbon::now()->addDays(25)->toDateString(), 'estimated_hours' => 40],
        ['phase_name' => 'IMPLEMENTACION', 'start_date' => Carbon::now()->addDays(26)->toDateString(), 'end_date' => Carbon::now()->addDays(28)->toDateString(), 'estimated_hours' => 16],
    ];

    return [
        'consultant' => $consultant,
        'requirement' => $requirement,
        'validPhases' => $validPhases,
    ];
}

/**
 * Escenario 1: Registro exitoso de la estimación (Happy Path)
 */
it('registers a complete estimation successfully and locks the requirement', function () {
    $data = getEstimationTestData();

    actingAs($data['consultant'], 'api')
        ->postJson("/api/core/requirements/{$data['requirement']->id}/estimation", [
            'requirement_id' => $data['requirement']->id, // <-- SATISFACEMOS AL FORM REQUEST
            'phases' => $data['validPhases'],
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    // Verificamos en la BD que se guardó la cabecera
    assertDatabaseHas('core.schedule_estimations', [
        'requirement_id' => $data['requirement']->id,
        'created_by' => $data['consultant']->id,
    ]);

    // Verificamos que el requerimiento quedó bloqueado (Hard Gate)
    assertDatabaseHas('core.requirements', [
        'id' => $data['requirement']->id,
        'is_locked' => true,
    ]);
});

/**
 * Escenario 2: Falla de validación por ausencia de fechas obligatorias
 */
it('fails if phases are incomplete or missing', function () {
    $data = getEstimationTestData();
    // Quitamos una fase para simular un envío incompleto
    $invalidPhases = array_slice($data['validPhases'], 0, 5);

    actingAs($data['consultant'], 'api')
        ->postJson("/api/core/requirements/{$data['requirement']->id}/estimation", [
            'requirement_id' => $data['requirement']->id,
            'phases' => $invalidPhases
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phases']);
});

/**
 * Escenario 3: Falla de validación por inconsistencia en el rango interno
 */
it('fails if a phase end_date is before its start_date', function () {
    $data = getEstimationTestData();
    $invalidPhases = $data['validPhases'];
    // Alteramos la primera fase para que termine antes de empezar
    $invalidPhases[0]['end_date'] = Carbon::now()->subDays(1)->toDateString();

    actingAs($data['consultant'], 'api')
        ->postJson("/api/core/requirements/{$data['requirement']->id}/estimation", [
            'requirement_id' => $data['requirement']->id, 
            'phases' => $invalidPhases
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phases.0.end_date']);
});

/**
 * Escenario 4: Falla de validación por ruptura de la secuencia lógica del proyecto
 */
it('fails if sequentiality is broken between phases', function () {
    $data = getEstimationTestData();
    $invalidPhases = $data['validPhases'];
    // Hacemos que el Diseño empiece ANTES que el ATF
    $invalidPhases[1]['start_date'] = Carbon::now()->subDays(1)->toDateString();

    actingAs($data['consultant'], 'api')
        ->postJson("/api/core/requirements/{$data['requirement']->id}/estimation", [
            'requirement_id' => $data['requirement']->id, 
            'phases' => $invalidPhases
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Error de coherencia cronológica.');
});

/**
 * Escenario 5: Falla de validación por fecha previa a la creación
 */
it('fails if the first phase starts before the requirement creation date', function () {
    $data = getEstimationTestData();
    $invalidPhases = $data['validPhases'];
    // Hacemos que la estimación empiece hace 5 días (el requerimiento se creó hace 2)
    $invalidPhases[0]['start_date'] = Carbon::now()->subDays(5)->toDateString();

    actingAs($data['consultant'], 'api')
        ->postJson("/api/core/requirements/{$data['requirement']->id}/estimation", [
            'requirement_id' => $data['requirement']->id, 
            'phases' => $invalidPhases
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});