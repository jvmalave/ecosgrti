<?php

use App\Domains\Security\Models\User;
use App\Domains\Audit\Models\AuditLog;

test('Escenario 01: Protección de rutas mediante Middleware RBAC', function () {
    // GIVEN: un usuario autenticado con el rol "operator"
    // Forzamos el campo JSONB con el rol correspondiente
    $operador = User::factory()->create([
        'roles' => ['operator'] 
    ]);

    // Autenticamos al usuario en la prueba usando el guard de la API
    // Esto simula que el usuario ya tiene un token JWT válido
    $this->actingAs($operador, 'api');

    // WHEN: intenta acceder a un endpoint administrativo protegido por "role:admin"
    // Simularemos que existe una ruta administrativa genérica
    $respuesta = $this->getJson('/api/dashboard');

    // THEN: el sistema debe retornar un código 403 (Forbidden)
    $respuesta->assertStatus(403);
});


test('Escenario 02: Registro de auditoría por cambio de privilegios', function () {
    // GIVEN: Un administrador y un usuario en el sistema
    $admin = User::factory()->create(['roles' => ['admin']]);
    $usuario = User::factory()->create(['roles' => ['operator']]);

    // Simulamos que el admin está logueado haciendo la petición
    $this->actingAs($admin, 'api');

    // WHEN: Se guardan los cambios en el campo JSONB "roles"
    $usuario->roles = ['operator', 'manager'];
    $usuario->save();

    // THEN: El sistema inserta automáticamente un registro en Audit.audit_logs
    // Buscamos que exista un log asociado al ID del admin con una acción específica
    $this->assertDatabaseHas('audit.audit_logs', [
        'user_id' => $admin->id,
        'action'  => 'ROLE_UPDATED',
    ]);

    // AND: El log debe detallar UUID del afectado, valores anteriores y nuevos
    $log = AuditLog::where('user_id', $admin->id)->first();
    
    // Decodificamos el payload para verificar su contenido
    $payload = json_decode($log->payload, true);

    expect($payload)
        ->toHaveKey('subject_id', $usuario->id)
        ->toHaveKey('old_roles', ['operator'])
        ->toHaveKey('new_roles', ['operator', 'manager']);
});
