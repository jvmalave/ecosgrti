<?php

namespace App\Domains\Security\Http\Controllers;



use App\Http\Controllers\Controller;
use App\Domains\Security\Http\Requests\LoginRequest; // Nuestro validador
use App\Domains\Security\Services\AuthService; // Nuestro gestor de lógica
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth; // Para autenticación
use App\Domains\Audit\Services\AuditService; // Para registrar eventos de auditoría
use Illuminate\Support\Facades\Log; // Para registrar errores de auditoría laravel.log
use Illuminate\Http\Request; // Para manejar la solicitud en logout
use OpenApi\Attributes as OA;
use App\Domains\Security\Docs\AuthDocs; // Para implementar la interfaz de documentación
use Illuminate\Support\Facades\Cache; // Para manejar la cache


#[OA\Info(title: "ECOSGRTI API", version: "1.0.0", description: "Documentación de Seguridad para el Sistema de Gestión de Requerimientos TI")]
#[OA\Server(url: "http://127.0.0.1:8000", description: "Servidor Local")]


class AuthController extends Controller implements AuthDocs
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
            ], 423);
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

        try {
            $this->auditService->store(
                'LOGIN_SUCCESS', 
                "Inicio de sesión exitoso: {$user->email}", 
                $request,
                $user->id
            );
        } catch (\Exception $e) {
            // Registramos el error en storage/logs/laravel.log para revisarlo luego
            Log::error("Fallo registro de auditoría US01: " . $e->getMessage());
        }

        // 4. Guardar el token en la cache (Paso 28)

        Cache::put(
            'user_session_' . $user->id, 
            $token, 
            Auth::factory()->getTTL() * 60
        );
        
        return $this->respondWithToken($token);
    }

    // Método para cerrar sesión (Paso 24 del Diagrama)

    public function logout(Request $request)
    {
        try {
            // Obtenemos al usuario antes de invalidar el token para la auditoría
            $user = auth()->user();

            // 1. Invalidar el token actual
            auth()->logout();

            // 2. Registrar en Auditoría (Paso 24 del flujo, pero para éxito de salida)
            $this->auditService->store(
                'LOGOUT',
                "Cierre de sesión exitoso para el usuario: {$user->email}",
                $request,
                $user->id
            );

            return response()->json(['message' => 'Sesión cerrada exitosamente']);

        } catch (\Exception $e) {
            // Si algo falla (ej. el sistema de auditoría), registramos el error interno
            Log::error("Error en Logout US01: " . $e->getMessage());
            
            // Aun si la auditoría falla, el usuario debería sentir que salió
            return response()->json(['message' => 'Sesión finalizada'], 200);
        }
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
                'email' => Auth::user()->email,
                // Aquí agregaremos la foto más adelante
            ]
        ], 200);
    }
}

