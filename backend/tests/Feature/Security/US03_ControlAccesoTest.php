<?php

use App\Domains\Security\Models\User; 
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

// Refrescamos la BD en cada prueba
uses(RefreshDatabase::class);

// ========================================================================
// Criterio T03.1 y T03.2: Middleware Activo y Autorización Jerárquica
// ========================================================================
it('Escenario: Acceso denegado a rutas restringidas por nivel de jerarquía', function () {
    
    // 1. Ruta simulada
    Route::middleware(['role:Coord'])->get('/api/test-restringido', function () {
        return response()->json(['message' => 'Acceso concedido']);
    });

    // 2. Creación y asignación explícita del usuario
    
    $consultor = User::factory()->create([
    'roles' => ['Consultant'], // Cambiado de json_encode a Array nativo
]);
    assert($consultor instanceof Authenticatable);

    // 3. Petición HTTP
    $response = actingAs($consultor)->getJson('/api/test-restringido');

    // 5. Aserción
    $response->assertStatus(403);
});

// ========================================================================
// Criterio T03.3: Auditoría (UserObserver y Snapshot Forense)
// ========================================================================
it('Escenario: Registro inmutable de auditoría al modificar privilegios', function () {
    
    // 1. PREPARACIÓN: Admin autenticado
    $admin = User::factory()->create();
    assert($admin instanceof Authenticatable);
    actingAs($admin);

    // 2. Creación del usuario a modificar
    $usuario = User::factory()->create([
    'roles' => ['Consultant'], // 💡 Cambiado de json_encode a Array nativo
]);

    // 3. ACCIÓN: Modificación de roles
    $usuario->roles = ['Coord'];
    $usuario->save(); 

    // 4. VERIFICACIÓN: Comprobación en base de datos
    assertDatabaseHas('audit.audit_logs', [
        'action'      => 'ROLE_UPDATED',
        'description' => "Se modificaron los privilegios del usuario {$usuario->email}",
    ]);
});