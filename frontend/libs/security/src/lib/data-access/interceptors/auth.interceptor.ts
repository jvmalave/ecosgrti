import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from '../services/auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  // 1. Inyectamos de manera funcional el servicio de autenticación 
  const authService = inject(AuthService);
  
  // 2. Obtenemos el usuario o sesión actual (usando el Signal de solo lectura)

  const session = authService.currentSession();

  // 3. Si el usuario está autenticado y tiene un token válido, clonamos la petición
  if (session && session.token) {
    const clonedRequest = req.clone({
      setHeaders: {
        Authorization: `Bearer ${session.token}`
      }
    });
    
    // Pasamos la petición clonada con el token adjunto 
    return next(clonedRequest);
  }

  // 4. Si no hay token (por ejemplo, en el Login), la petición sigue su curso original
  return next(req);
};