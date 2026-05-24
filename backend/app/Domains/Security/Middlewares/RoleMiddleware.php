<?php

namespace App\Domains\Security\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Obtenemos al usuario que está intentando acceder
        $user = $request->user();

        // 2. Si no hay usuario, o si el rol exigido NO está dentro de su arreglo de roles...
        // (El casting de Eloquent convierte el JSONB en un array nativo de PHP)
        if (! $user || ! in_array($role, $user->roles ?? [])) {
            // ...le cerramos la puerta devolviendo el código 403 (Forbidden)
            abort(403, 'Acceso denegado. Privilegios insuficientes.');
        }

        // 3. Si tiene el rol, lo dejamos pasar al siguiente paso
        return $next($request);
    }
}
