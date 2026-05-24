<?php

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use function Pest\Laravel\artisan;

test('Escenario 01: Alerta por inconsistencias detectadas (Registro Huérfano)', function () {
    // GIVEN: Un registro huérfano en el esquema "Audit"
    $uuidHuerfano = Str::uuid()->toString(); 
    
    AuditLog::create([
        'user_id'     => $uuidHuerfano,
        'action'      => 'ORPHAN_TEST',
        'description' => 'Registro de prueba para verificar integridad',
        'ip_address'  => '127.0.0.1',
        'user_agent'  => 'Pest/Testing',
        'payload'     => json_encode([]),
    ]);

    // THEN: El sistema debe escribir una alerta CRITICAL en los logs
    // Verificamos que el log incluya la palabra "FALLO DE INTEGRIDAD REFERENCIAL"
    // tal como lo escribiste en tu comando.
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function ($mensaje) {
            return str_contains($mensaje, 'FALLO DE INTEGRIDAD REFERENCIAL');
        });

    // WHEN: Se ejecuta el comando de consola
    // Usamos el helper artisan() de Pest para ejecutar el comando
    artisan('domains:verify-integrity')
        ->expectsOutputToContain('inconsistencias. Alerta crítica')
        ->assertExitCode(1); // Esperamos que retorne el código de error 1 que definiste
});

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\Event;

test('Escenario 02: Ejecución programada y concurrencia (Atomic Locks)', function () {
    // GIVEN: Obtenemos el planificador de tareas activo en la aplicación
    $schedule = app(Schedule::class);

    // Extraemos todos los eventos programados actuales
    $eventos = collect($schedule->events());

    // Buscamos nuestro comando específico en la lista de tareas
    $comandoProgramado = $eventos->first(function (Event $evento) {
        return str_contains($evento->command, 'domains:verify-integrity');
    });

    // THEN: Verificamos que el comando exista en el planificador
    expect($comandoProgramado)->not->toBeNull('El comando no está programado en el Scheduler.');

    // AND: Verificamos que tenga configurado el bloqueo atómico para evitar solapamientos
    expect($comandoProgramado->withoutOverlapping)->toBeTrue('El comando no tiene activado withoutOverlapping().');
});