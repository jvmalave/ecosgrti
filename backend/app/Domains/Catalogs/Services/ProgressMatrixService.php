<?php

namespace App\Domains\Catalogs\Services;

use App\Domains\Catalogs\Models\ProgressMatrix;
use App\Domains\Catalogs\Models\ProgressMatrixMilestone;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;


class ProgressMatrixService
{
  public function __construct(protected AuditService $auditService) {}


  //OBTIENE LA MATRIZ ACTIVA SEGUÑA EL TIPO DE GESTIÓN -IMPLEMENTA PATRON CACHE-ASIDE CON REDIS
  public function getActiveMatrix(string $managementType): ?ProgressMatrix
{
    Cache::forget("active_matrix_{$managementType}");
    
    $matrix = ProgressMatrix::with([
        'milestones' => function ($query) {
            // Ordenamos haciendo referencia directa a la columna sort_order de la tabla milestones
            $query->join('catalogs.milestones as m', 'catalogs.progress_matrix_milestones.milestone_id', '=', 'm.id')
                  ->orderBy('m.sort_order', 'asc')
                  ->select('catalogs.progress_matrix_milestones.*'); 
        },
        'milestones.milestone'
    ])
    ->where('management_type', $managementType)
    ->where('is_active', true)
    ->first();

    if (!$matrix) {
        throw new \Exception("Inconsistencia MDM: No se encontró matriz activa para el tipo de gestión '{$managementType}'[cite: 1].");
    }

    return $matrix;
}

  // PUBLICA UNA NUEVA VERSION DE LA MATRIZ DE PROGRESO (VERSONAMIENTO INMUTABLE)
  public function publishNewVersion(string $managementType, array $milestones): ProgressMatrix
  {
    return DB::transaction(function () use ($managementType, $milestones) {
      // BLOQUEO PESIMISTA DE LA MATRIZ ACTIVA ACTUAL
      $currentActive = ProgressMatrix::where('management_type', $managementType)
        ->where('is_active', true)
        ->lockForUpdate()
        ->first();

      $newVersionNumber = 1;
      $oldVersionId = null;

      if ($currentActive) {
        $newVersionNumber = $currentActive->version_number + 1;
        $oldVersionId = $currentActive->id;
        // CONMUTADOR DE ESTADO: INACTIVAR LA VERSIÓN ANTERIOR SIN BORRARLA
        $currentActive->update(['is_active' => false]);
      }
      // CREAR LA NUEVA VERSIÓN (SNAPSHOT INMUTABLE FOTOGRAFICO)
      $newMatrix = ProgressMatrix::create([
        'management_type' => $managementType,
        'version_number' => $newVersionNumber,
        'is_active' => true,
      ]);
      // PREPARAR E INSERTAR LOS HITOS CON SUS PONDERACIONES MATEMÁTICAS EXACTAS
      $milestonesData = array_map(function ($item) use ($newMatrix) {
        return [
          'id' => (string) Str::uuid(),
          'matrix_id' => $newMatrix->id,
          'milestone_id' => $item['milestone_id'],
          'weight_percentage' => $item['weight'],
          'created_at' => now(),
          'updated_at' => now(),
        ];
      }, $milestones);

      // INSERCCION EN BLOQUE PARA OPTIMIZAR I/O
      ProgressMatrixMilestone::insert($milestonesData);

      // REGISTRO FORENCE DE AUDITORÍA DINÁMICA
      $this->auditService->logModelChange(
        'VERSION_PUBLISH',
        "Publicación de nueva versión {$newVersionNumber} de Matriz de Progreso: {$managementType}",
        [
          'record_id' => $newMatrix->id,
          'old_version_id' => $oldVersionId,
          'new_milestones' => $milestones
        ],
        auth()->id(),
        $newMatrix->id
      );
      // INVALIDACION DE CASHE EN REDIS PARA FORZAR LA LECTURA DE LA NUEVA VERSION
      Cache::forget("active_matrix_{$managementType}");

      return $newMatrix;
    });
  }
}
