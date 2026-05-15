<?php

namespace App\Domains\Security\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Security\Requests\LoginRequest; // Nuestro validador
use App\Domains\Security\Services\AuthService; // Nuestro gestor de lógica
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth; // Para autenticación
use App\Domains\Audit\Services\AuditService; // Para registrar eventos de auditoría


class AuthController extends Controller
{
    protected AuthService $authService;
    protected AuditService $auditService; // Para registrar eventos de auditoría

    /**
     * El constructor recibe el servicio automáticamente.
     */
    public function __construct(AuthService $authService, AuditService $auditService)
    {
        $this->authService = $authService;
        $this->auditService = $auditService;
    }

    /**
     * Punto de entrada para la US01: Autenticación.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $email = $credentials['email'];

        // 1. Verificar bloqueo en Redis (Paso 16 del Diagrama)
        if ($this->authService->isLockedOut($email)) {
            return response()->json([
                'error' => 'Demasiados intentos. Por favor, espere 15 minutos.'
            ], 429);
        }

        // 2. Intento de autenticación (Paso 22 al 25 del Diagrama)
        if (!$token = Auth::attempt($credentials)) {
            // Credenciales Inválidas: Incrementamos intentos en Redis
            $this->authService->incrementAttempts($email);
            
            // Registro de Auditoría para Fallo
            $this->auditService->store(
              'LOGIN_FAIL', 
              "Intento de acceso fallido para el correo: {$email}", 
              $request
            );
            
            return response()->json([
                'error' => 'Credenciales no válidas'
            ], 401);
        }

        // 3. Credenciales Válidas: Reset de intentos y respuesta (Paso 27)
        $this->authService->resetAttempts($email);

        // Registro de Auditoría para Éxito
        $user = Auth::user();

        $this->auditService->store(
            'LOGIN_SUCCESS', 
            "Inicio de sesión exitoso para el usuario: {$user->email}", 
            $request,
            $user->id //
        );
        
        return $this->respondWithToken($token);
    }
/**
 * Formatear la respuesta con el token (Paso 30)
 */
  protected function respondWithToken(string $token): JsonResponse 
  {
      return response()->json([
          'access_token' => $token,
          'token_type' => 'bearer',
          'expires_in' => Auth::factory()->getTTL() * 60,
          'user' => [
              'name' => Auth::user()->name,
              // Aquí agregaremos la foto más adelante
          ]
      ]);
  }
}