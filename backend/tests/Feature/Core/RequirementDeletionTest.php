<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

/**
 * Helper para generar el requerimiento con toda su jerarquía
 */

// Apagamos los middlewares (como RoleMiddleware) para concentrarnos 100% en el Core
beforeEach(function () {
    \Pest\Laravel\withoutMiddleware();
});

function getRequirementTestData(): array
{
    $user = User::factory()->create();

    // Construcción del Grafo Jerárquico
    $societyId = (string) Str::uuid();
    $systemId = (string) Str::uuid();
    $requestingUnitId = (string) Str::uuid(); 
    $personId = (string) Str::uuid(); 
    $functionalConsultantId = (string) Str::uuid();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SISTEMA CORE', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $requestingUnitId, 'system_id' => $systemId, 'name' => 'Unidad de Pruebas', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'Consultor', 'last_name' => 'Test', 'email' => 'consultor.test@example.com', 'phone' => '04121234567', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $functionalConsultantId, 'person_id' => $personId, 'requesting_unit_id' => $requestingUnitId, 'created_at' => now(), 'updated_at' => now()]);

    $requirement = new Requirement();
    $requirement->id = (string) Str::uuid();
    $requirement->rrti = 'RRTI-' . rand(1000, 9999);
    $requirement->requirement_type = 'NUEVO SISTEMA';
    $requirement->creation_date = Carbon::now()->subDays(2)->toDateString();
    $requirement->description = 'Descripción a ser borrada lógicamente.';
    $requirement->management_type = 'DESARROLLO INTERNO'; 
    $requirement->snapshot_society_name = 'CANTV';
    $requirement->snapshot_system_name = 'SISTEMA CORE';
    $requirement->snapshot_unit_name = 'GERENCIA DE TECNOLOGÍA';
    $requirement->functional_consultant_id = $functionalConsultantId; 
    $requirement->status = 'PL';
    $requirement->is_locked = false;
    $requirement->created_at = Carbon::now()->subDays(2);
    $requirement->save();

    return [
        'user' => $user,
        'requirement' => $requirement,
    ];
}

/**
 * Escenario 1: Borrado lógico exitoso y generación de Ticket en Redis
 */
it('soft deletes the requirement and validates a redis ticket', function () {
    $data = getRequirementTestData();
    $fakeTicket = 'TICKET-9999';

    // 1. MOCKING DE SEGURIDAD: Simulamos que el ticket se consume con éxito
    $this->mock(\App\Domains\Security\Services\SpecialOperationService::class, function ($mock) {
        $mock->shouldReceive('consumeDeletionTicket')
             ->once() // Verificamos que tu controlador realmente llame a esta barrera
             ->andReturnNull(); // Asumiendo que es una función 'void'
    });

    // 2. MOCKING DE AUDITORÍA: Ignoramos el guardado físico del log
    $this->mock(\App\Domains\Audit\Services\AuditService::class, function ($mock) {
        $mock->shouldReceive('logModelChange')
            ->zeroOrMoreTimes()
            ->andReturnNull();
    });

    // 3. Ejecutamos la petición de borrado
    actingAs($data['user'], 'api')
        ->deleteJson("/api/core/requirements/{$data['requirement']->id}", [
            'deletion_ticket' => $fakeTicket, 
            'justification' => 'El requerimiento fue cargado por error en el sistema.',
        ])
        ->assertStatus(200) 
        ->assertJsonPath('success', true);

    // 4. Verificamos la base de datos (SoftDelete)
    assertSoftDeleted('core.requirements', [
        'id' => $data['requirement']->id,
    ]);
});
/**
 * Escenario 2: Falla al intentar borrar un requerimiento bloqueado
 */
it('fails to delete a locked requirement', function () {
    $data = getRequirementTestData();
    
    // Bloqueamos el requerimiento manualmente
    $data['requirement']->is_locked = true;
    $data['requirement']->save();

    actingAs($data['user'], 'api')
        ->deleteJson("/api/core/requirements/{$data['requirement']->id}", [
            'deletion_ticket' => 'TICKET-' . rand(1000, 9999),
            'justification' => 'Intento de borrado ilegal.',
        ])
        // NOTA: Si tu controlador devuelve un 403 (Forbidden) o 400 (Bad Request) por estar bloqueado, 
        ->assertStatus(403) 
        ->assertJsonPath('success', false);
});