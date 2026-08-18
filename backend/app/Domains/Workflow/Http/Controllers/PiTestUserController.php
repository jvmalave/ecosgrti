<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Workflow\Http\Requests\StorePiTestUserRequest;
use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Services\PiTestUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Exception;

class PiTestUserController extends Controller
{
    public function __construct(private readonly PiTestUserService $testUserService) {}

    public function index(string $roleId): JsonResponse
    {
        return response()->json([
            'data' => $this->testUserService->getTestUsersByRole($roleId)
        ], 200);
    }

    public function store(StorePiTestUserRequest $request, string $roleId): JsonResponse
    {
        try {
            $user = $this->testUserService->storeTestUser(
                $roleId,
                $request->requirement_id,
                $request->identifier,
                $request->force ?? false,
                (string) auth()->id()
            );

            return response()->json(['message' => 'Usuario registrado con éxito', 'data' => $user], 201);
            
        } catch (HttpResponseException $e) {
            throw $e;
            
        } catch (Exception $e) {
            // 3. Este bloque ahora solo procesará errores estándar o 422
            $status = $e->getCode() === 422 ? 422 : 500;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function destroy(string $userId): JsonResponse
    {
        try {
            $this->testUserService->deleteTestUser($userId, (string) auth()->id());
            return response()->json(['message' => 'Usuario eliminado correctamente'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() === 422 ? 422 : 500);
        }
    }
}