<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Http\Requests\StorePiRegisterRequest;
use App\Domains\Workflow\Http\Requests\UpdatePiRegisterRequest;
use App\Domains\Workflow\Services\PiRegisterService;
use Exception;

class PiRegisterController extends Controller
{
    public function __construct(private readonly PiRegisterService $registerService) {}

    public function index(string $roleId): JsonResponse
    {
        // Retorna el objeto formateado exactamente como Angular lo espera
        return response()->json(
            $this->registerService->getRegistersFormatted($roleId), 
            200
        );
    }

    public function store(StorePiRegisterRequest $request, string $reqId, string $roleId): JsonResponse
    {
        try {
            // Extrae los datos validados del request (título, descripción, fecha)
            $data = $request->validated();
            
            // Agrega el requirement_id que exige la tabla pi_registers
            $data['requirement_id'] = $reqId;

            // 3. Envia el data enriquecido al motor transaccional
            $record = $this->registerService->storeRegister($roleId, $data, $reqId);
            
            return response()->json($record, 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() === 403 ? 403 : 500);
        }
    }

    public function update(UpdatePiRegisterRequest $request, string $regId, string $roleId): JsonResponse
    {
        try {
            $record = $this->registerService->updateRegister($regId, $roleId, $request->validated(), (string) auth()->id());
            return response()->json($record, 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() === 403 ? 403 : 500);
        }
    }

    public function destroy(string $regId, string $roleId): JsonResponse
    {
        try {
            $this->registerService->deleteRegister($regId, $roleId, (string) auth()->id());
            return response()->json(['message' => 'Registro eliminado correctamente.'], 200);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [403, 422]) ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }
}