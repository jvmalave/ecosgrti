<?php

namespace App\Domains\Security\Http\Controllers;



use App\Http\Controllers\Controller;
use App\Domains\Security\Http\Requests\LoginRequest; // Validador
use App\Domains\Security\Services\AuthService; // Gestor de lógica
use Illuminate\Http\JsonResponse; // Respuesta JSON
use Illuminate\Support\Facades\Auth; // Autenticación
use App\Domains\Audit\Services\AuditService; // Registrar eventos de auditoría
use Illuminate\Support\Facades\Log; // Registrar errores de auditoría laravel.log
use Illuminate\Http\Request; // Manejar la solicitud en logout
use OpenApi\Attributes as OA;
use App\Domains\Security\Docs\AuthDocs; // Implementar la interfaz de documentación
use Illuminate\Support\Facades\Cache; // Manejar la cache


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
     * Método de Autenticación.
     */

        public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $email = $credentials['email'];

        // Verificar si el usuario esta bloqueado
        if ($this->authService->isLockedOut($email)) {
            return response()->json([
                'error' => 'Demasiados intentos. Por favor, espere 15 minutos.'
            ], 423);
        }

        // Valida Intentos de autenticación
        if (!$token = Auth::attempt($credentials)) {
            // Credenciales Inválidas: Incrementamos intentos en Redis
            $this->authService->incrementAttempts($email);
            
            //Registro de Auditoría para Fallo
            $this->auditService->store(
              'LOGIN_FAIL', 
              "Intento de acceso fallido para el correo: {$email}", 
              $request
            );
            
            return response()->json([
                'error' => 'Credenciales no válidas'
            ], 401);
        }

        // Credenciales Válidas: Reset de intentos y respuesta 
        $this->authService->resetAttempts($email);

        //Registro de Auditoría para Éxito
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

        // Guardar el token en la cache

        Cache::put(
            'user_session_' . $user->id, 
            $token, 
            Auth::factory()->getTTL() * 60
        );
        
        return $this->respondWithToken($token);
    }

    // Método para cerrar sesion

    public function logout(Request $request)
    {
        try {
            //  Obtener el usuario antes de invalidar el token para la auditoría
            $user = auth()->user();

            // Invalidar el token actual
            auth()->logout();

            // Registrar en Auditoría el cierre de sesión
            $this->auditService->store(
                'LOGOUT',
                "Cierre de sesión exitoso para el usuario: {$user->email}",
                $request,
                $user->id
            );

            return response()->json(['message' => 'Sesión cerrada exitosamente']);

        } catch (\Exception $e) {
            // Si algo falla, se registra el error interno
            Log::error("Error en Logout US01: " . $e->getMessage());
            
            // Salida sin auditoría: Aun si la auditoría falla, el usuario debería sentir que salió
            return response()->json(['message' => 'Sesión finalizada'], 200);
        }
    }
/**
 * Formatear la respuesta con el token 
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

