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
    protected AuditService $auditService; 

    public function __construct(AuthService $authService, AuditService $auditService)
    {
        $this->authService = $authService;
        $this->auditService = $auditService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // 🟢 Capturamos el 'username' desde el frontend (antes era email)
        $username = $request->input('username');
        $password = $request->input('password');

        // Verificar si el usuario esta bloqueado en Redis
        if ($this->authService->isLockedOut($username)) {
            return response()->json([
                'error' => 'Demasiados intentos. Por favor, espere 15 minutos.'
            ], 423);
        }

        // 🟢 Validar Intentos de autenticación mapeando el 'username' a la columna 'name'
        if (!$token = Auth::attempt(['name' => $username, 'password' => $password])) {
            
            // Credenciales Inválidas: Incrementamos intentos en Redis
            $this->authService->incrementAttempts($username);
            
            // Registro de Auditoría para Fallo (Ocultando el correo)
            $this->auditService->store(
              'LOGIN_FAIL', 
              "Intento de acceso fallido para el usuario corporativo: {$username}", 
              $request
            );
            
            return response()->json([
                'error' => 'Credenciales no válidas'
            ], 401);
        }

        // Credenciales Válidas: Reset de intentos
        $this->authService->resetAttempts($username);

        $user = Auth::user();

        try {
            // Registro de Auditoría para Éxito (Ocultando el correo)
            $this->auditService->store(
                'LOGIN_SUCCESS', 
                "Inicio de sesión exitoso: {$user->name}", 
                $request,
                $user->id
            );
        } catch (\Exception $e) {
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

    public function logout(Request $request)
    {
        try {
            $user = auth()->user();
            auth()->logout();

            // 🟢 Registro en Auditoría (Usando el 'name' en lugar de email)
            $this->auditService->store(
                'LOGOUT',
                "Cierre de sesión exitoso para el usuario: {$user->name}",
                $request,
                $user->id
            );

            return response()->json(['message' => 'Sesión cerrada exitosamente']);

        } catch (\Exception $e) {
            Log::error("Error en Logout US01: " . $e->getMessage());
            return response()->json(['message' => 'Sesión finalizada'], 200);
        }
    }

  protected function respondWithToken(string $token): JsonResponse 
    {
        $user = Auth::user();

        // 🟢 EXTRACCIÓN DIRECTA (Bypass de Eloquent para evitar pérdidas de formato)
        $rawRoles = \Illuminate\Support\Facades\DB::table('security.users')
                        ->where('id', $user->id)
                        ->value('roles');
        
        // Decodificamos el JSON puro de la base de datos a un arreglo de PHP
        $rolesArray = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;

        $person = \Illuminate\Support\Facades\DB::table('security.persons')
                        ->where('id', $user->id)
                        ->first();
        
        // Buscamos las columnas (nombres/apellidos o first_name/last_name según como las tengas)
        $nombres = $person->nombres ?? $person->first_name ?? '';
        $apellidos = $person->apellidos ?? $person->last_name ?? '';
        $fullName = trim($nombres . ' ' . $apellidos);
        
        // Si por alguna razón la persona no tiene nombre registrado, usamos el username como respaldo
        $fullName = !empty($fullName) ? $fullName : $user->name;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name, 
                'username' => $user->name, 
                'fullName' => $fullName, 
                'email'    => $user->email,
                'roles'    => !empty($rolesArray) ? $rolesArray : [], // 🟢 Obligamos a enviar el arreglo real
            ]
        ], 200);
    }
}