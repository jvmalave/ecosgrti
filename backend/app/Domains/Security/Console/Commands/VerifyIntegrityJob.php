<?php

namespace App\Domains\Security\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('domains:verify-integrity')]
#[Description('Command description')]
class VerifyIntegrityJob extends Command
{
    /**
     * Execute the console command.
     */
public function handle()
{
    $resultados = DB::select("
        SELECT COUNT(*) as total 
        FROM audit.audit_logs al
        WHERE al.user_id IS NOT NULL 
          AND NOT EXISTS (
              SELECT 1 
              FROM security.users u
              WHERE u.id = al.user_id
          )
    ");

    $totalHuerfanos = $resultados[0]->total;

    if ($totalHuerfanos > 0) {
        // Usamos el nivel critical con información descriptiva para facilitar la identificación del problema en los logs
        Log::critical("FALLO DE INTEGRIDAD REFERENCIAL: Se encontraron {$totalHuerfanos} registros huérfanos en la tabla 'audit.audit_logs' que no coinciden con ningún usuario en 'security.users'.");
        
        $this->error("Se encontraron {$totalHuerfanos} inconsistencias. Alerta crítica registrada en logs.");
        return 1; // Retornamos código de error para el Scheduler
    }

    $this->info("Integridad referencial verificada con éxito. No se encontraron registros huérfanos.");
    return 0;
}
}
