<?php

use App\Domains\Security\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;



// Usamos este trait para que la base de datos en memoria (SQLite) 
// se construya y se limpie automáticamente en cada prueba.
uses(RefreshDatabase::class);

test('Escenario 01: Inicio de sesión exitoso y generación de Token', function () {
    
    // GIVEN: El usuario "admin@cantv.com.ve" con contraseña "secret123" existe
    $user = User::factory()->create([
        'email' => 'admin@cantv.com.ve',
        'password' => bcrypt('secret123'),
    ]);

    // WHEN: Envío una petición POST a la ruta de login con las credenciales correctas
    $response = $this->postJson('/api/auth/login', [
        'email' => 'admin@cantv.com.ve',
        'password' => 'secret123',
    ]);

    // THEN: El código de respuesta debe ser 200
    $response->assertStatus(200);

    // AND: El cuerpo debe contener un "access_token"
    $response->assertJsonStructure([
        'access_token',
        'token_type',
        'expires_in'
    ]);
});

test('Escenario 02: Persistencia de estado en Redis', function () {
    
    // 1. Limpiamos Redis antes de la prueba para evitar falsos positivos
    Redis::flushall();

    // GIVEN: Un usuario que va a iniciar sesión
    User::factory()->create([
        'email' => 'admin@cantv.com.ve',
        'password' => bcrypt('secret123'),
    ]);

    // Simulamos el inicio de sesión para que el servidor genere el JWT
    // Simulamos el inicio de sesión y guardamos la respuesta
    $response = $this->postJson('/api/auth/login', [
        'email' => 'admin@cantv.com.ve',
        'password' => 'secret123',
    ]);


    // Verificamos que el login haya sido exitoso antes de buscar en Redis
    $response->assertStatus(200);

    // WHEN: Consulto el servidor de Redis mediante "keys *"
    // En Laravel, Redis::keys('*') devuelve un arreglo con todas las llaves activas
    $keys = (array) Redis::connection('cache')->keys('*user_session_*');


    // THEN: Debe existir una llave (el arreglo no debe estar vacío)
    expect($keys)->not->toBeEmpty();

    // Verificamos que alguna de las llaves contenga el prefijo esperado de la sesión/token
    $llaveEncontrada = collect($keys)->contains(function ($key) {
        return str_contains($key, 'user_session_');
    });
    
    expect($llaveEncontrada)->toBeTrue();

    // AND: El tiempo de expiración (TTL) debe ser consistente
    // Tomamos el nombre exacto de la primera llave que encontramos
    $llaveFisica = $keys[0] ?? '';
    
    // Pedimos el TTL (Time To Live) de esa llave específica
    // (Limpiamos el prefijo si estamos usando phpredis)
    $prefijoCache = Cache::getPrefix();
    $llaveParaConsulta = strstr($llaveFisica, $prefijoCache);
    
    $ttl = Redis::connection('cache')->ttl($llaveParaConsulta);

    // El TTL debe ser mayor a 0 (lo que significa que tiene expiración y no es infinita)
    expect($ttl)->toBeGreaterThan(0);
});

test('Escenario 03: Bloqueo de cuenta tras 3 intentos fallidos', function () {
    // 1. Limpiamos Redis para que no haya bloqueos "fantasma"
    Redis::flushall();

    // GIVEN: Creamos al usuario analista
    User::factory()->create([
        'email' => 'analista@cantv.com.ve',
        'password' => bcrypt('ClaveCorrecta123'),
    ]);

    // WHEN: Ingresa una contraseña errónea por 3 veces consecutivas
    for ($intento = 1; $intento <= 3; $intento++) {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'analista@cantv.com.ve',
            'password' => 'clave_equivocada', // ❌ Contraseña intencionalmente mala
        ]);

        // Verificamos que el sistema rechace cada intento con un 401
        $response->assertStatus(401); 
    }

    // THEN: El sistema debe registrar un bloqueo en Redis
    // Hacemos un 4to intento con la clave correcta, pero debería rebotar igual.
    $respuestaBloqueo = $this->postJson('/api/auth/login', [
        'email' => 'analista@cantv.com.ve',
        'password' => 'ClaveCorrecta123', 
    ]);

    // AND: cualquier intento posterior debe retornar un código 423 (Locked)
    $respuestaBloqueo->assertStatus(423);
});

test('Escenario 04: Intento de acceso con Token expirado (Robustez)', function () {
    Redis::flushall();

    // ⏱️ Truco TDD: Forzamos temporalmente a que los tokens nazcan expirados
    config(['jwt.ttl' => -1]);

    // GIVEN: Un usuario válido
    User::factory()->create([
        'email' => 'expirado@cantv.com.ve',
        'password' => bcrypt('secret123'),
    ]);

    // Iniciamos sesión para obtener el token "vencido"
    $loginResponse = $this->postJson('/api/auth/login', [
        'email' => 'expirado@cantv.com.ve',
        'password' => 'secret123',
    ]);
    
    $token = $loginResponse->json('access_token');

    // WHEN: Intenta acceder a un recurso protegido (/api/auth/logout)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->postJson('/api/auth/logout');

    // 🕵️‍♂️ Mantenemos el dump para ver cómo responde Tymon JWT ante la expiración
    $response->dump();

    // THEN: El sistema debe retornar un código 401 (Unauthorized)
    $response->assertStatus(401);
});

test('Escenario 05: Intento de acceso con Token manipulado (Robustez)', function () {
    // Limpiamos Redis por precaución
    Redis::flushall();

    // GIVEN: Un usuario válido
    $user = User::factory()->create([
        'email' => 'hacker@cantv.com.ve',
        'password' => bcrypt('secret123'),
    ]);

    // 🧠 TRUCO DEFINITIVO: Fabricamos el token directamente usando el ID, 
    // sin iniciar sesión ni guardar nada en la memoria de Laravel.
    $tokenValido = auth('api')->tokenById($user->id);

    // WHEN: El cliente modifica maliciosamente la firma del token
    $tokenManipulado = $tokenValido . 'x';

    // AND envía la petición al servidor (Esta será nuestra ÚNICA petición HTTP)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $tokenManipulado,
    ])->postJson('/api/auth/logout');

    // 🕵️‍♂️ Imprimimos para confirmar que ahora sí explota
    $response->dump();

    // THEN: el middleware debe retornar 401
    $response->assertStatus(401);
});


