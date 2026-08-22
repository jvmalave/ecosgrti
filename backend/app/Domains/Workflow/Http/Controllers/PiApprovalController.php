<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Http\Requests\StorePiApprovalRequest;
use App\Domains\Workflow\Services\PiApprovalService;
use Illuminate\Http\JsonResponse;
use Exception;

class PiApprovalController extends Controller
{
    public function __construct(private readonly PiApprovalService $approvalService) {}

    /**
     * Registra la aprobación funcional para un rol de Pruebas Integrales.
     */
    public function store(StorePiApprovalRequest $request, string $reqId, string $roleId): JsonResponse
    {
        try {
            // Extraemos el archivo y las observaciones del request validado
            $approval = $this->approvalService->storeApproval(
                $reqId,
                $roleId,
                $request->file('file'),
                (string) auth()->id()
            );

            return response()->json([
                'message' => 'Aprobación funcional registrada con éxito.',
                'data' => $approval
            ], 201);
            
        } catch (Exception $e) {
            // Mapeo de códigos de error HTTP según las reglas de negocio del servicio
            $status = in_array($e->getCode(), [403, 422]) ? $e->getCode() : 500;
            
            return response()->json([
                'message' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * Descarga o visualiza el acta de aprobación funcional de un rol.
     */
    public function download(string $reqId, string $roleId)
    {
        try {
            // Delegamos la búsqueda y validación de existencia al servicio
            $fileData = $this->approvalService->getApprovalFile($roleId);

            // Retornamos el archivo para que el navegador lo muestre de forma segura
            return response()->file($fileData['path'], [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileData['original_name'] . '"'
            ]);

        } catch (Exception $e) {
            $status = in_array($e->getCode(), [404]) ? $e->getCode() : 500;

            return response()->json([
                'message' => $e->getMessage()
            ], $status);
        }
    }
}