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
        // 1. Obtenemos los roles del usuario autenticado
        $userRoles = $request->user()->roles;

        // 2. SOLUCIÓN: Si la base de datos nos devuelve un string (JSON), lo convertimos a Arreglo
        if (is_string($userRoles)) {
            $userRoles = json_decode($userRoles, true) ?? [];
        }

        // 3. Comparamos los arreglos
        if (empty(array_intersect($userRoles, $roles))) {
            return response()->json(['message' => 'Acceso denegado. Privilegios insuficientes.'], 403);
        }

        return $next($request);
    }

    private function checkUserHasAnyRole(User $user, array $roles): bool
{
    return count(array_intersect($user->roles, $roles)) > 0;
}
}
