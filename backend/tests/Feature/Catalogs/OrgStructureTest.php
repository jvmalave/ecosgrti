<?php

use App\Domains\Catalogs\Models\Society;
use App\Domains\Catalogs\Models\System;
use App\Domains\Catalogs\Models\RequestingUnit;
use App\Domains\Security\Models\Person;
use App\Domains\Security\Models\User;
use App\Domains\Security\Models\FunctionalConsultant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Helper: Crea y retorna un usuario Administrador aislado.
 */
function createAdminUser(): User
{
    $uuid = Str::uuid()->toString();
    $person = Person::create([
        'first_name' => 'Admin',
        'last_name' => 'Sistema',
        'email' => "admin.{$uuid}@cantv.com.ve",
        'phone' => '04160000000',
    ]);

    return User::create([
        'id' => $person->id,
        'name' => "admin_{$uuid}",
        'email' => $person->email,
        'password' => bcrypt('password123'),
        'password_updated_at' => now(), // <-- Corregido el nombre de la columna
        'roles' => ['admin'],
    ]);
}

/**
 * Helper: Crea y retorna un usuario Coordinador aislado.
 */
function createCoordUser(): User
{
    $uuid = Str::uuid()->toString();
    $person = Person::create([
        'first_name' => 'Coordinador',
        'last_name' => 'Gestión',
        'email' => "coord.{$uuid}@cantv.com.ve",
        'phone' => '04162222222',
    ]);

    return User::create([
        'id' => $person->id,
        'name' => "coord_{$uuid}",
        'email' => $person->email,
        'password' => bcrypt('password123'),
        'password_updated_at' => now(),
        'roles' => ['coord'],
    ]);
}

/**
 * Helper: Crea y retorna un usuario Consultor aislado.
 */
function createConsultantUser(): User
{
    $uuid = Str::uuid()->toString();
    $person = Person::create([
        'first_name' => 'Consultor',
        'last_name' => 'Prueba',
        'email' => "consultor.{$uuid}@cantv.com.ve",
        'phone' => '04161111111',
    ]);

    return User::create([
        'id' => $person->id,
        'name' => "consultant_{$uuid}",
        'email' => $person->email,
        'password' => bcrypt('password123'),
        'password_updated_at' => now(), // <-- Corregido el nombre de la columna
        'roles' => ['consultant'],
    ]);
}

// 1. PRUEBA DE RECHAZO (403): El consultor no puede acceder
test('RBAC: Deniega el acceso a la estructura organizacional a usuarios sin rol admin o coord', function () {
    $consultantUser = createConsultantUser();

    $this->actingAs($consultantUser, 'api')
        ->getJson('/api/catalogs/org-structure/tree')
        ->assertStatus(403);
});

// 2. PRUEBA DE ÉXITO (201): El admin sí puede registrar
test('HAPPY PATH: Permite al admin registrar Sociedad, Sistema y Unidad Solicitante', function () {
    $adminUser = createAdminUser();

    // 1. Crear Sociedad
    $societyResponse = $this->actingAs($adminUser, 'api')
        ->postJson('/api/catalogs/org-structure/societies', [
            'name' => 'CANTV MATRIZ ' . Str::random(5),
            'acronym' => 'C' . strtoupper(Str::random(4)),
        ])
        ->assertStatus(201);

    $societyId = $societyResponse->json('data.id');

    // 2. Crear Sistema bajo la Sociedad
    $systemResponse = $this->actingAs($adminUser, 'api')
        ->postJson('/api/catalogs/org-structure/systems', [
            'society_id' => $societyId,
            'name' => 'SISTEMA DE GESTION CSPE',
        ])
        ->assertStatus(201);

    $systemId = $systemResponse->json('data.id');

    // 3. Crear Unidad Solicitante bajo el Sistema
    $this->actingAs($adminUser, 'api')
        ->postJson('/api/catalogs/org-structure/requesting-units', [
            'system_id' => $systemId,
            'name' => 'COORDINACION DE SEGURIDAD PORTALES',
        ])
        ->assertStatus(201);
});

// 3. PRUEBA DE ÉXITO (201): El coordinador también puede registrar
test('HAPPY PATH: Permite al coord registrar entidades en el catálogo', function () {
    $coordUser = createCoordUser();

    $this->actingAs($coordUser, 'api')
        ->postJson('/api/catalogs/org-structure/societies', [
            'name' => 'CANTV SUCURSAL ' . Str::random(5),
            'acronym' => 'S' . strtoupper(Str::random(4)),
        ])
        ->assertStatus(201);
});

test('FS-01: Rechaza registro de nombre duplicado bajo la misma entidad padre', function () {
    $adminUser = createAdminUser();
    $society = Society::create(['name' => 'CANTV S1 ' . Str::random(4), 'acronym' => 'S1' . Str::random(2)]);
    $system = System::create(['society_id' => $society->id, 'name' => 'SISTEMA BASE']);

    RequestingUnit::create(['system_id' => $system->id, 'name' => 'UNIDAD OPERATIVA']);

    $this->actingAs($adminUser, 'api')
        ->postJson('/api/catalogs/org-structure/requesting-units', [
            'system_id' => $system->id,
            'name' => 'unidad operativa',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('FS-02: Impide inactivar un Sistema si posee Unidades Solicitantes activas', function () {
    $adminUser = createAdminUser();
    $society = Society::create(['name' => 'CANTV S2 ' . Str::random(4), 'acronym' => 'S2' . Str::random(2)]);
    $system = System::create(['society_id' => $society->id, 'name' => 'SISTEMA CRITICO']);
    RequestingUnit::create(['system_id' => $system->id, 'name' => 'UNIDAD DEPENDIENTE', 'is_active' => true]);

    $this->actingAs($adminUser, 'api')
        ->patchJson("/api/catalogs/org-structure/systems/{$system->id}/status", [
            'is_active' => false
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);
});

test('FS-03: Impide inactivar una Unidad Solicitante si posee Consultores Funcionales activos', function () {
    $adminUser = createAdminUser();
    $consultantUser = createConsultantUser();

    $society = Society::create(['name' => 'CANTV S3 ' . Str::random(4), 'acronym' => 'S3' . Str::random(2)]);
    $system = System::create(['society_id' => $society->id, 'name' => 'SISTEMA CORE']);
    $unit = RequestingUnit::create(['system_id' => $system->id, 'name' => 'UNIDAD CON CONSULTOR']);

    FunctionalConsultant::create([
        'person_id' => $consultantUser->id,
        'requesting_unit_id' => $unit->id,
    ]);

    $this->actingAs($adminUser, 'api')
        ->patchJson("/api/catalogs/org-structure/requesting-units/{$unit->id}/status", [
            'is_active' => false
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);
});

test('REACTIVACION: Impide reactivar una Unidad si el Sistema padre está inactivo', function () {
    $adminUser = createAdminUser();
    $society = Society::create(['name' => 'CANTV S4 ' . Str::random(4), 'acronym' => 'S4' . Str::random(2)]);
    $system = System::create(['society_id' => $society->id, 'name' => 'SISTEMA INACTIVO', 'is_active' => false]);
    $unit = RequestingUnit::create(['system_id' => $system->id, 'name' => 'UNIDAD HUERFANA', 'is_active' => false]);

    $this->actingAs($adminUser, 'api')
        ->patchJson("/api/catalogs/org-structure/requesting-units/{$unit->id}/status", [
            'is_active' => true
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);
});

test('CACHE & AUDIT: El cambio de estatus de un nodo invalida Redis y registra log inmutable', function () {
    Cache::spy();

    $adminUser = createAdminUser();
    $society = Society::create(['name' => 'CANTV S5 ' . Str::random(4), 'acronym' => 'S5' . Str::random(2)]);
    $system = System::create(['society_id' => $society->id, 'name' => 'SISTEMA LIBRE']);

    $this->actingAs($adminUser, 'api')
        ->patchJson("/api/catalogs/org-structure/systems/{$system->id}/status", [
            'is_active' => false
        ])
        ->assertStatus(200);

    Cache::shouldHaveReceived('forget')->with('catalogs_org_tree');
    Cache::shouldHaveReceived('forget')->with('catalogs_active_units');

    $this->assertDatabaseHas('audit.audit_logs', [
        'user_id' => $adminUser->id,
        'action' => 'DEACTIVATE',
        'target_id' => $system->id,
    ]);
});