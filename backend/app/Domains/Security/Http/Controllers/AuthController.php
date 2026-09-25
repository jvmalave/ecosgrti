<?php

namespace App\Domains\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Security\Http\Requests\LoginRequest;
use App\Domains\Security\Http\Requests\ChangePasswordRequest; 
use App\Domains\Security\Services\AuthService;
use App\Domains\Security\Services\PasswordSecurityService; 
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use App\Domains\Security\Docs\AuthDocs;
use Illuminate\Support\Facades\Cache;

#[OA\Info(title: "ECOSGRTI API", version: "1.0.0", description: "Documentación de Seguridad para el Sistema de Gestión de Requerimientos TI")]
#[OA\Server(url: "http://127.0.0.1:8000", description: "Servidor Local")]
class AuthController extends Controller implements AuthDocs
{
    protected AuthService $authService;
    protected AuditService $auditService;
    protected PasswordSecurityService $passwordSecurityService; 

    public function __construct(
        AuthService $authService, 
        AuditService $auditService,
        PasswordSecurityService $passwordSecurityService
    ) {
        $this->authService = $authService;
        $this->auditService = $auditService;
        $this->passwordSecurityService = $passwordSecurityService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $username = $request->input('username');
        $password = $request->input('password');

        if ($this->authService->isLockedOut($username)) {
            return response()->json([
                'error' => 'Demasiados intentos. Por favor, espere 15 minutos.'
            ], 423);
        }

        if (!$token = Auth::attempt(['name' => $username, 'password' => $password])) {
            
            $this->authService->incrementAttempts($username);
            
            $this->auditService->store(
              'LOGIN_FAIL', 
              "Intento de acceso fallido para el usuario corporativo: {$username}", 
              $request
            );
            
            return response()->json([
                'error' => 'Credenciales no válidas'
            ], 401);
        }

        $this->authService->resetAttempts($username);

        $user = Auth::user();

        try {
            $this->auditService->store(
                'LOGIN_SUCCESS', 
                "Inicio de sesión exitoso: {$user->name}", 
                $request,
                $user->id
            );
        } catch (\Exception $e) {
            Log::error("Fallo registro de auditoría US01: " . $e->getMessage());
        }

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

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var \App\Domains\Security\Models\User $user */
        $user = $request->user();

        // Delega la lógica al Service, respetando el SRP
        $this->passwordSecurityService->changeUserPassword(
            $user,
            $request->validated('current_password'),
            $request->validated('new_password'),
            $user->id 
        );

        // Registro de Auditoría del cambio de contraseña
        try {
            $this->auditService->store(
                'PASSWORD_CHANGE',
                "El usuario {$user->name} actualizó su contraseña y renovó su periodo de caducidad.",
                $request,
                $user->id
            );
        } catch (\Exception $e) {
            Log::error("Fallo registro de auditoría en cambio de clave: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Tu contraseña ha sido actualizada exitosamente y tu periodo de validez ha sido renovado.'
        ]);
    }

    protected function respondWithToken(string $token): JsonResponse 
    {
        $user = Auth::user();

        $rawRoles = \Illuminate\Support\Facades\DB::table('security.users')
                        ->where('id', $user->id)
                        ->value('roles');
        
        $rolesArray = is_string($rawRoles) ? json_decode($rawRoles, true) : (array) $rawRoles;

        $person = \Illuminate\Support\Facades\DB::table('security.persons')
                        ->where('id', $user->id)
                        ->first();
        
        $nombres = $person->nombres ?? $person->first_name ?? '';
        $apellidos = $person->apellidos ?? $person->last_name ?? '';
        $fullName = trim($nombres . ' ' . $apellidos);
        
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
                'roles'    => !empty($rolesArray) ? $rolesArray : [], 
            ]
        ], 200);
    }
}