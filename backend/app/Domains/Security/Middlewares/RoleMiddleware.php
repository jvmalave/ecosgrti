<?php

namespace App\Domains\Security\Middlewares;

use App\Domains\Security\Models\User;
use Closure;
use Illuminate\Http\Request;


class RoleMiddleware
{
  /**
   * Handle an incoming request.
   */
  

  public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        
        // Extrae los roles gracias al Cast del Modelo
        $userRoles = $user->roles ?? []; 
        
        // Si PostgreSQL lo entrega como String JSON plano en la nube,se forza a Array
        if (is_string($userRoles)) {
            $userRoles = json_decode($userRoles, true) ?? [];
        }

        // Si por alguna razón no es un array válido, se inicializa como vacío para prevenir fallos en array_map
        if (!is_array($userRoles)) {
            $userRoles = [];
        }

        // Se Normalizan ambos arreglos a minúsculas (lowercase) 
        // para garantizar una comparación tolerante a errores de tipeo en la BD o en las Rutas.
        $normalizedUserRoles = array_map('strtolower', $userRoles);
        $normalizedRequiredRoles = array_map('strtolower', $roles);

        // Comparación usando los arreglos normalizados
        if (empty(array_intersect($normalizedUserRoles, $normalizedRequiredRoles))) {
            
            \Illuminate\Support\Facades\Log::warning('Bloqueo RBAC: Intento de acceso sin privilegios', [
                'user_id' => $user->id,
                'user_roles_detected' => $userRoles,
                'required_roles' => $roles,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Privilegios insuficientes.'
            ], 403);
        }

        return $next($request);
    }

  private function checkUserHasAnyRole(User $user, array $roles): bool
  {
    return count(array_intersect($user->roles, $roles)) > 0;
  }
}
