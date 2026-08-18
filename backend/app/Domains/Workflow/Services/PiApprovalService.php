<?php

namespace App\Domains\Workflow\Services;

use Illuminate\Support\Facades\Storage;
use App\Domains\Workflow\Models\PiRole;
use App\Domains\Workflow\Models\PiFunctionalApproval;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
// 🟢 Importaciones críticas para la reactividad
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Domains\Core\Dictionaries\CacheKeyDictionary;
use Exception;

class PiApprovalService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function storeApproval(string $reqId, string $roleId, UploadedFile $file, string $userId): array
    {
        $role = PiRole::findOrFail($roleId);

        if ($role->is_approved) {
            throw new Exception("Este rol ya cuenta con una aprobación funcional registrada.", 422);
        }

        if ($role->status !== 'IN_PROGRESS') {
            throw new Exception("No se puede registrar una aprobación para un rol que no se encuentra en progreso.", 403);
        }

        // OBTENER EL NOMBRE DEL CONSULTOR FUNCIONAL
        $consultant = DB::table('core.requirements as r')
            ->join('security.functional_consultants as fc', 'r.functional_consultant_id', '=', 'fc.id')
            ->join('security.persons as p', 'fc.person_id', '=', 'p.id')
            ->where('r.id', $reqId)
            ->select('p.first_name', 'p.last_name')
            ->first();

        $approverName = $consultant 
            ? "{$consultant->first_name} {$consultant->last_name}" 
            : 'Consultor Funcional Asignado';

        // EXTRACCIÓN DE METADATOS DEL ARCHIVO
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        // Gestión del Archivo Físico
        $timestamp = now()->format('Ymd_His');
        $safeFilename = "acta_aprobacion_{$reqId}_role_{$roleId}_{$timestamp}.pdf";
        
        $path = $file->storeAs("pi_approvals/{$reqId}", $safeFilename, 'local');

        if (!$path) {
            throw new Exception("Fallo interno al intentar guardar el documento en el servidor.", 500);
        }

        // Transacción de Base de Datos
        $approval = DB::transaction(function () use ($role, $path, $approverName, $originalName, $fileSize, $userId) {
            
            $newApproval = PiFunctionalApproval::create([
                'pi_role_id'    => $role->id,
                'approver_name' => $approverName,
                'date'          => now()->toDateString(), 
                'file_path'     => $path,
                'original_name' => $originalName,
                'file_size'     => $fileSize,
                'created_by'    => $userId
            ]);

            $role->update([
              'is_approved' => true,
              'updated_by'  => $userId
            ]);

            $this->auditService->logModelChange(
                'SAVE_PI_APPROVAL',
                "Se registró la aprobación funcional del rol por {$approverName}. Archivo: {$originalName}",
                ['role_id' => $role->id, 'file_path' => $path],
                $userId,
                $newApproval->id
            );

            return $newApproval;
        });

        // =====================================================================
        // DOBLE INVALIDACIÓN Y REACTIVIDAD DEL FRONTEND
        // =====================================================================
        
        // 1. Destruir la caché de roles de PI para que al consultar de nuevo venga is_approved = true
        $listKey = CacheKeyDictionary::phaseComponentsList($reqId, 'PI-I');
        Cache::forget($listKey);
        Redis::del($listKey);

        // 2. Incrementar la versión global para forzar al frontend a actualizarse
        Redis::incr(CacheKeyDictionary::globalDashboardVersion());

        return $approval->toArray();
    }

    public function getApprovalFile(string $roleId): array
    {
        $approval = PiFunctionalApproval::where('pi_role_id', $roleId)->first();

        if (!$approval) {
            throw new Exception('No se encontró un acta de aprobación para este rol.', 404);
        }

        if (!Storage::disk('local')->exists($approval->file_path)) {
            throw new Exception('El archivo físico no se encuentra en el servidor. Póngase en contacto con soporte.', 404);
        }

        return [
            'path' => Storage::disk('local')->path($approval->file_path),
            'original_name' => $approval->original_name
        ];
    }
}