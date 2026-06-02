<?php

use App\Domains\Security\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


// test('Escenario 01: Verificación de estructura multiesquema', function () {
//     // 1. Obtenemos los esquemas actuales de la base de datos
//     $esquemas = DB::select('SELECT schema_name FROM information_schema.schemata');
    
//     // Convertimos el resultado en un arreglo simple de textos
//     $nombresDeEsquemas = collect($esquemas)->pluck('schema_name')->toArray();

//     // THEN: Deben estar presentes exactamente los esquemas requeridos
//     $esquemasRequeridos = ['security', 'catalogs', 'core', 'audit', 'ia', 'workflow'];
//     expect($nombresDeEsquemas)->toContain(...$esquemasRequeridos);

//     // 2. Verificamos que el esquema "public" no tenga tablas
//     $tablas = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    
//     // AND el esquema "public" debe permanecer vacío
//     expect($tablas)->toBeEmpty();
// });


test('Escenario 01: Verificación de estructura multiesquema', function () {
    // 1. Verificamos que existan los esquemas de nuestra arquitectura
    $esquemas = DB::select("SELECT schema_name FROM information_schema.schemata");
    $nombresDeEsquemas = collect($esquemas)->pluck('schema_name')->toArray();

    $esquemasRequeridos = ['security', 'catalogs', 'core', 'audit', 'ia', 'workflow'];
    expect($nombresDeEsquemas)->toContain(...$esquemasRequeridos);

    // 2. Verificamos que el esquema "public" no tenga tablas de negocio
    // 💡 SOLUCIÓN: Agregamos el WHERE para excluir la tabla de control de Laravel
    $tablas = DB::select("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
          AND table_name != 'migrations'
    ");
    
    // AND el esquema "public" debe permanecer vacío de tablas de negocio
    expect($tablas)->toBeEmpty();
});


test('Escenario 02: Uso de UUID como identificador primario', function () {
    // 1. Verificamos la generación automática de UUID v4
    $usuario = User::factory()->create();
    $idGenerado = $usuario->id;
    
    expect(Str::isUuid($idGenerado))->toBeTrue();

    // 2. Verificamos que rechace un ID entero
    expect(fn() => User::factory()->create(['id' => 1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});


test('Escenario 03: Implementación de Soft Deletes y Auditoría', function () {
    // 1. Creamos un usuario en la base de datos
    $usuario = User::factory()->create();

    // 2. Ejecutamos la acción de eliminar
    $usuario->delete();

    // 3. Verificamos que no se haya borrado físicamente
    $this->assertSoftDeleted($usuario);
});



test('Escenario 04: Prevención de colisiones de UUID (Robustez)', function () {
    // GIVEN & WHEN: Un proceso de inserción masiva de 1000 registros concurrentes
    // La fábrica se encarga de crear y persistir los registros en la base de datos
    $usuarios = User::factory()->count(1000)->create();

    // Extraemos exclusivamente la columna 'id' de la colección de usuarios
    $idsGenerados = $usuarios->pluck('id');

    // Filtramos la colección para dejar solo los IDs que sean estrictamente únicos
    $idsUnicos = $idsGenerados->unique();

    // THEN: El sistema garantiza que no existan duplicados
    // Si la cantidad de IDs únicos es exactamente 1000, significa que no hubo ninguna colisión
    expect($idsGenerados->count())->toBe(1000)
        ->and($idsUnicos->count())->toBe(1000);
        
    // AND: La base de datos validó la restricción "Primary Key"
    // Esto se valida implícitamente: si hubiera existido un duplicado, 
    // PostgreSQL habría abortado la operación lanzando una QueryException antes de llegar al expect().
});