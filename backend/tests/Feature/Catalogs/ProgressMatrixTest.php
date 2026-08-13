<?php

use App\Domains\Security\Models\User;
use App\Domains\Catalogs\Models\ProgressMatrix;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\withoutMiddleware;

// RN-Aislamiento Total: Refresca la base de datos de pruebas antes de cada ejecución
uses(RefreshDatabase::class);

beforeEach(function () {
    // Desactivamos el middleware perimetral (RBAC/Auth) para aislar la prueba
    // estrictamente en la validación matemática (Hard Gate) y la base de datos.
    withoutMiddleware();
});

function getAdminUser(): Authenticatable {
    /** @var User $user */
    $user = User::factory()->createOne();
    
    // Si tu middleware lo requiere, descomenta la siguiente línea:
    // $user->assignRole('admin'); 
    
    return $user;
}

/**
 * Función auxiliar para sembrar hitos y pasar la validación "exists:catalogs.milestones,id"
 */
function seedMilestones(array $uuids, string $type) {
    foreach ($uuids as $uuid) {
        DB::table('catalogs.milestones')->insert([
            'id' => $uuid,
            'management_type' => $type,
            'name' => 'Hito Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

it('rechaza la publicación si la suma de los hitos no es exactamente 100%', function () {
    $m1 = Str::uuid()->toString();
    $m2 = Str::uuid()->toString();
    seedMilestones([$m1, $m2], 'ENTREGABLES');

    $payload = [
        'management_type' => 'ENTREGABLES',
        'milestones' => [
            ['milestone_id' => $m1, 'weight' => 50.00],
            ['milestone_id' => $m2, 'weight' => 49.99], // Suma 99.99 (Error FS-01)
        ]
    ];

    actingAs(getAdminUser(), 'api')
        ->postJson(route('matrix.publish'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['milestones']);
});

it('rechaza la publicación si un hito tiene un peso inferior a 0.01%', function () {
    $m1 = Str::uuid()->toString();
    $m2 = Str::uuid()->toString();
    seedMilestones([$m1, $m2], 'ROLES');

    $payload = [
        'management_type' => 'ROLES',
        'milestones' => [
            ['milestone_id' => $m1, 'weight' => 100.00],
            ['milestone_id' => $m2, 'weight' => 0.00], // Falla regla min:0.01 (FS-03)
        ]
    ];

    actingAs(getAdminUser(), 'api')
        ->postJson(route('matrix.publish'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['milestones.1.weight']);
});

it('publica exitosamente una nueva matriz, inactiva la anterior y registra auditoria', function () {
    $m1 = Str::uuid()->toString();
    $m2 = Str::uuid()->toString();
    seedMilestones([$m1, $m2], 'MIXTO');

    $oldMatrix = ProgressMatrix::create([
        'management_type' => 'MIXTO',
        'version_number' => 1,
        'is_active' => true
    ]);

    $payload = [
        'management_type' => 'MIXTO',
        'milestones' => [
            ['milestone_id' => $m1, 'weight' => 60.00],
            ['milestone_id' => $m2, 'weight' => 40.00], // Suma 100.00% exacta
        ]
    ];

    $response = actingAs(getAdminUser(), 'api')
        ->postJson(route('matrix.publish'), $payload);

    $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['matrix_id', 'version_number']]);

    $newMatrixId = $response->json('data.matrix_id');

    assertDatabaseHas('catalogs.progress_matrices', [
        'id' => $oldMatrix->id,
        'is_active' => false
    ]);

    assertDatabaseHas('catalogs.progress_matrices', [
        'id' => $newMatrixId,
        'version_number' => 2,
        'is_active' => true
    ]);

    assertDatabaseHas('catalogs.progress_matrix_milestones', [
        'matrix_id' => $newMatrixId,
        'milestone_id' => $m1,
        'weight_percentage' => 60.00
    ]);

    assertDatabaseHas('audit.audit_logs', [
        'action' => 'VERSION_PUBLISH',
        'target_id' => $newMatrixId
    ]);
});