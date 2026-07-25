<?php

declare(strict_types=1);

use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementPhaseHistory;
use App\Domains\Workflow\Services\ProgressCalculationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$consultantId = '';

beforeEach(function () use (&$consultantId) {
    Config::set('workflow_progress.matrices.Mixto', [
        'RC' => 4.0, 'ES-R' => 1.0, 'ATF-I' => 5.0, 'OVERFLOW' => 96.0
    ]);

    $societyId = Str::uuid()->toString();
    $systemId = Str::uuid()->toString();
    $unitId = Str::uuid()->toString();
    $personId = Str::uuid()->toString();
    $consultantId = Str::uuid()->toString();

    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'CANTV ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'SGRTI ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'system_id' => $systemId, 'name' => 'CSPE ' . Str::random(5), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'j@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('security.functional_consultants')->insert(['id' => $consultantId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);
});
// Escenario principal
it('calcula el progreso correctamente', function () use (&$consultantId) {
    $service = new ProgressCalculationService();
    $requirement = Requirement::factory()->create(['management_type' => 'Mixto', 'functional_consultant_id' => $consultantId]);

    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'RC']);
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'ES-R']);
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'ATF-I']);

    expect($service->calculateGlobalProgress($requirement))->toBe(10.0);
});
// Escenario recuperado para mantener la integridad
it('aplica el failsafe al 100 por ciento', function () use (&$consultantId) {
    $service = new ProgressCalculationService();
    $requirement = Requirement::factory()->create(['management_type' => 'Mixto', 'functional_consultant_id' => $consultantId]);

    // 4.0 (RC) + 1.0 (ES-R) + 5.0 (ATF-I) + 96.0 (OVERFLOW) = 106.0
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'RC']);
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'ES-R']);
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'ATF-I']);
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'OVERFLOW']);

    Log::shouldReceive('channel')->with('audit')->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
        return $context['calculated_raw'] === 106.0 && $context['normalized'] === 100.0;
    });

    expect($service->calculateGlobalProgress($requirement))->toBe(100.0);
});

// Escenario recuperado para mantener la integridad
it('ignora codigos de estado que no estan definidos en la matriz', function () use (&$consultantId) {
    $service = new ProgressCalculationService();
    $requirement = Requirement::factory()->create(['management_type' => 'Mixto', 'functional_consultant_id' => $consultantId]);

    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'RC']); // 4.0
    RequirementPhaseHistory::factory()->create(['requirement_id' => $requirement->id, 'phase_status_code' => 'CODIGO_FANTASMA']);

    expect($service->calculateGlobalProgress($requirement))->toBe(4.0);
});

// Escenario adicional de robustez (Valor mínimo 0)
it('garantiza que el progreso no sea negativo', function () use (&$consultantId) {
    $service = new ProgressCalculationService();
    // Requerimiento sin historial de fases
    $requirement = Requirement::factory()->create(['management_type' => 'Mixto', 'functional_consultant_id' => $consultantId]);

    expect($service->calculateGlobalProgress($requirement))->toBe(0.0);
});