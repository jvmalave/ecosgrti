<?php

namespace App\Domains\Security\Middlewares;

use App\Domains\Security\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, array ... $roles): Response
    {
        // 1. Obtenemos al usuario que está intentando acceder
        $user = $request->user();

        // 2. Verificamos si el usuario tiene alguno de los roles requeridos
        if (! $user || ! $this->checkUserHasAnyRole($user, $roles)) {
        abort(403, 'Acceso denegado. Privilegios insuficientes.');
    }

        // 3. Si tiene el rol, lo dejamos pasar al siguiente paso
        return $next($request);
    }

    private function checkUserHasAnyRole(User $user, array $roles): bool
{
    return count(array_intersect($user->roles, $roles)) > 0;
}
}
