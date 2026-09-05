<?php

namespace App\Domains\Security\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsNotExpired
{
    /**
     * Maneja la petición entrante para validar la vigencia de la contraseña.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Evaluamos si es su primer ingreso (null) o si ya pasaron 45 días
            $isPasswordExpired = is_null($user->password_updated_at) || 
                                  $user->password_updated_at->diffInDays(now()) >= 45;

            if ($isPasswordExpired) {
                // EXCEPCIÓN CRÍTICA: Rutas permitidas para evitar un bucle de bloqueos.
                // Verifica que estas URIs coincidan exactamente con tus rutas reales.
                $allowedRoutes = [
                    'api/auth/change-password',
                    'api/auth/logout',
                ];

                if (!$request->is($allowedRoutes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tu contraseña ha caducado por políticas de seguridad (45 días) o es tu primer ingreso. Por favor, actualízala para continuar operando en el sistema.'
                    ], 426); // 426 Upgrade Required
                }
            }
        }

        return $next($request);
    }
}