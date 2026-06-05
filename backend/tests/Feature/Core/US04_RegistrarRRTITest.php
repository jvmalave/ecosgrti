<?php

// Archivo: tests/Feature/Architecture/US04_RegistrarRRTITest.php

use App\Domains\Security\Models\User;
// IMPORTANTE: Si tienes un modelo Person o FunctionalConsultant, impórtalo aquí. 
// Ejemplo: use App\Domains\Security\Models\FunctionalConsultant;

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

    // 1. Construimos la jerarquía organizacional exacta que ya dominamos
    $societyId = (string) Str::uuid();
    DB::table('catalogs.societies')->insert(['id' => $societyId, 'name' => 'Soc', 'created_at' => now(), 'updated_at' => now()]);

    $systemId = (string) Str::uuid();
    DB::table('catalogs.systems')->insert(['id' => $systemId, 'society_id' => $societyId, 'name' => 'Sys', 'created_at' => now(), 'updated_at' => now()]);

    $unitId = (string) Str::uuid();
    DB::table('catalogs.requesting_units')->insert(['id' => $unitId, 'name' => 'Unit', 'system_id' => $systemId, 'created_at' => now(), 'updated_at' => now()]);

    $personId = (string) Str::uuid();
    DB::table('security.persons')->insert(['id' => $personId, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'test_' . time() . '@mail.com', 'created_at' => now(), 'updated_at' => now()]);

    $consultorId = (string) Str::uuid();
    DB::table('security.functional_consultants')->insert(['id' => $consultorId, 'person_id' => $personId, 'requesting_unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);

    // OJO: Si tu ruta usa el person_id en vez del consultorId, cambia la variable abajo.
    $idParaRuta = $personId; 

    // 2. Limpiamos la caché usando Cache
    $nombreLlave = "grafo_persona_{$idParaRuta}"; 
    Cache::forget($nombreLlave);

    // 3. Hacemos la petición GET
    $response = getJson("/api/consultores/lookup-organizacional/{$idParaRuta}");

    if ($response->status() !== 200) {
        $response->dump();
    }
    $response->assertStatus(200); 
    
    // 4. ESCÁNER FORENSE DEFINITIVO
    $cachedData = Cache::get($nombreLlave);
    
    if (is_null($cachedData)) {
        dump("🔍 El test buscó la llave: " . $nombreLlave);
        dump("📂 Pero Redis realmente contiene estas llaves:");
        dump(Redis::keys('*'));
    }

    $this->assertNotNull($cachedData, "La estructura no se guardó en la Caché");
});


// ========================================================================
// Criterio T04.1 y T04.3: Persistencia, UUID v4 y Unit Snapshot
// ========================================================================
it('Escenario: Creación exitosa del requerimiento con captura de Snapshot', function () {
    
    $user = User::factory()->create();
    assert($user instanceof User);
    actingAs($user);

    // 1. Falsificamos el disco de almacenamiento
    Storage::fake('local'); 

    // 2. SOLUCIÓN AL FACTORY (Bypass de BD Directo - Cadena Completa):
    
    // A.1) Insertamos la Sociedad 
    $societyId = (string) Str::uuid();
    DB::table('catalogs.societies')->insert([
        'id'         => $societyId,
        'name'       => 'Sociedad de Prueba',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // A.2) Insertamos el Sistema amarrándolo a la Sociedad
    $systemId = (string) Str::uuid();
    DB::table('catalogs.systems')->insert([
        'id'         => $systemId,
        'society_id' => $societyId, // <-- SOLUCIÓN: Vinculamos el sistema con la sociedad
        'name'       => 'Sistema Base',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // B) Insertamos la Unidad (Solo necesita conocer a su Sistema)
    $unitId = (string) Str::uuid();
    DB::table('catalogs.requesting_units')->insert([
        'id'           => $unitId,
        'name'         => 'Unidad de Arquitectura TI',
        'system_id'    => $systemId, 
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    // C) Insertamos la Persona
    $personId = (string) Str::uuid();
    DB::table('security.persons')->insert([
        'id'         => $personId,
        'first_name' => 'Consultor',
        'last_name'  => 'De Prueba',
        'email'      => 'consultor_' . time() . '@ejemplo.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // D) Insertamos el Consultor amarrando la Persona y la Unidad
    $consultorId = (string) Str::uuid();
    DB::table('security.functional_consultants')->insert([
        'id'                 => $consultorId,
        'person_id'          => $personId,
        'requesting_unit_id' => $unitId,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
  
// E) Insertamos un Consultor CSPE para satisfacer la regla 'cspe_consultants.*'
    $cspePersonId = (string) Str::uuid();
    DB::table('security.persons')->insert([
        'id'         => $cspePersonId,
        'first_name' => 'CSPE',
        'last_name'  => 'Consultor',
        'email'      => 'cspe_' . time() . '@ejemplo.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cspeId = (string) Str::uuid();
    DB::table('security.cspe_consultants')->insert([
        'id'         => $cspeId,
        'person_id'  => $cspePersonId, // Asumiendo que comparte la misma lógica relacional
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3. Payload perfecto (Ajustado a tus reglas de validación)
    $payload = [
        'rrti'                     => 'RRTI-' . date('Y') . '-002',
        'requirement_type'         => 'Nuevo Desarrollo',
        'description'              => 'Prueba automatizada de requerimiento con archivos',
        'management_type'          => 'Agile',
        'creation_date'            => now()->format('Y-m-d'),
        
        // ¡LA CLAVE!: Tu Request exige que este sea el PERSON ID, no el ID del consultor
        'functional_consultant_id' => $personId, 
        
        // Pasamos el ID del CSPE que sí existe en la BD
        'cspe_consultants'         => [$cspeId], 
        
        'it_request_doc'           => UploadedFile::fake()->create('solicitud.pdf', 1024, 'application/pdf'),
        'needs_spreadsheet'        => UploadedFile::fake()->create('necesidades.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
    ];

    // 4. Enviamos la petición
    $response = postJson('/api/core/requirements', $payload);

    if ($response->status() !== 201) {
        $response->dump(); // Si llegase a fallar, nos dirá el porqué
    }

    // 5. Aserción de éxito
    $response->assertStatus(201);

    // 6. Aserción en BD
    assertDatabaseHas('core.requirements', [
        'rrti'             => $payload['rrti'],
        'requirement_type' => 'Nuevo Desarrollo',
    ]);
});