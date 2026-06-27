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

        // Extraemos los roles gracias al Cast del Modelo
        $userRoles = $user->roles ?? []; 

        // POR QUÉ: Normalizamos ambos arreglos a minúsculas (lowercase) 
        // para garantizar una comparación tolerante a errores de tipeo en la BD o en las Rutas.
        $normalizedUserRoles = array_map('strtolower', $userRoles);
        $normalizedRequiredRoles = array_map('strtolower', $roles);

        // Comparamos usando los arreglos normalizados
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
