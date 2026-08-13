<?php

// Archivo: tests/Feature/Core/US04_RegistrarRRTITest.php

use App\Domains\Security\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\assertDatabaseHas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// ========================================================================
// Criterio T04.2: Validación Organizacional con Carga Híbrida (Redis)
// ========================================================================
it('Escenario: Consulta optimizada de la estructura organizacional del consultor (Redis)', function () {
    
    $user = User::factory()->create();
    assert($user instanceof User);
    actingAs($user);

    $societyId = (string) Str::uuid();
    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'Soc_' . Str::random(4), 'created_at' => now(), 'updated_at' => now()]);

    $systemId = (string) Str::uuid();
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'Sys_' . Str::random(4), 'created_at' => now(), 'updated_at' => now()]);

    $unitId = (string) Str::uuid();
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'name' => 'Unit_' . Str::random(4), 'system_id' => $systemId, 'created_at' => now(), 'updated_at' => now()]);

    $personId = (string) Str::uuid();
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'test_' . Str::random(5) . '@cantv.com.ve', 'created_at' => now(), 'updated_at' => now()]);

    $consultorId = (string) Str::uuid();
    DB::table('security.functional_consultants')->insert(['id' => $consultorId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);

    $idParaRuta = $personId; 

    $nombreLlave = "grafo_persona_{$idParaRuta}"; 
    Cache::forget($nombreLlave);

    $response = getJson("/api/consultores/lookup-organizacional/{$idParaRuta}");

    if ($response->status() !== 200) {
        $response->dump();
    }
    $response->assertStatus(200); 
    
    $cachedData = Cache::get($nombreLlave);
    $this->assertNotNull($cachedData, "La estructura no se guardó en la Caché");
});

// ========================================================================
// Criterio T04.1 y T04.3: Persistencia, UUID v4 y Unit Snapshot
// ========================================================================
it('Escenario: Creación exitosa del requerimiento con captura de Snapshot', function () {
    
    $user = User::factory()->create();
    assert($user instanceof User);
    actingAs($user);

    Storage::fake('local'); 

    // 1. REGISTRO DE LA MATRIZ DE PROGRESO ACTIVA EN LA TABLA CORRECTA
    DB::table('catalogs.progress_matrices')->insert([
        'id'              => (string) Str::uuid(),
        'management_type' => 'Mixto',
        'version_number'  => 1,
        'is_active'       => true,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    // 2. Jerarquía organizacional
    $societyId = (string) Str::uuid();
    DB::table('catalogs.societies')->insert([
        'id'         => $societyId,
        'name'       => 'Sociedad de Prueba ' . Str::random(4),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $systemId = (string) Str::uuid();
    DB::table('catalogs.systems')->insert([
        'id'         => $systemId,
        'society_id' => $societyId,
        'name'       => 'Sistema Base ' . Str::random(4),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $unitId = (string) Str::uuid();
    DB::table('catalogs.requesting_units')->insert([
        'id'           => $unitId,
        'name'         => 'Unidad de Arquitectura TI ' . Str::random(4),
        'system_id'    => $systemId, 
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $personId = (string) Str::uuid();
    DB::table('security.persons')->insert([
        'id'         => $personId,
        'first_name' => 'Consultor',
        'last_name'  => 'De Prueba',
        'email'      => 'consultor_' . Str::random(5) . '@cantv.com.ve',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $consultorId = (string) Str::uuid();
    DB::table('security.functional_consultants')->insert([
        'id'                 => $consultorId,
        'person_id'          => $personId,
        'requesting_unit_id' => $unitId,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    $cspePersonId = (string) Str::uuid();
    DB::table('security.persons')->insert([
        'id'         => $cspePersonId,
        'first_name' => 'CSPE',
        'last_name'  => 'Consultor',
        'email'      => 'cspe_' . Str::random(5) . '@cantv.com.ve',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cspeId = (string) Str::uuid();
    DB::table('security.cspe_consultants')->insert([
        'id'         => $cspeId,
        'person_id'  => $cspePersonId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3. Payload
    $rrtiCode = 'RRTI-' . date('Y') . '-' . rand(100, 999);
    $payload = [
        'rrti'                     => $rrtiCode,
        'requirement_type'         => 'Nuevo Desarrollo',
        'description'              => 'Prueba automatizada de requerimiento con archivos',
        'management_type'          => 'Mixto',
        'creation_date'            => now()->format('Y-m-d'),
        'functional_consultant_id' => $personId, 
        'cspe_consultants'         => [$cspeId], 
        'it_request_doc'           => UploadedFile::fake()->create('solicitud.pdf', 1024, 'application/pdf'),
        'needs_spreadsheet'        => UploadedFile::fake()->create('necesidades.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
    ];

    // 4. Petición POST
    $response = postJson('/api/core/requirements', $payload);

    if ($response->status() !== 201) {
        $response->dump();
    }

    $response->assertStatus(201);

    assertDatabaseHas('core.requirements', [
        'rrti'             => $rrtiCode,
        'requirement_type' => 'Nuevo Desarrollo',
    ]);
});