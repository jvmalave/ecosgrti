import { inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common'; 
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const authGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);
  const platformId = inject(PLATFORM_ID); 

  // 1. Leemos el Signal público
  const session = authService.currentSession();

  // 2. Si hay sesión, adelante
  if (session && session.token) {
    return true;
  }

  // 3. Mecanismo de seguridad SSR (evitar redirecciones en el servidor)
  if (!isPlatformBrowser(platformId)) {
    return true; 
  }

  // 4. Si no hay sesión y estamos en el navegador, pa' fuera
  // Usar navigate es más directo y es lo que espera nuestra prueba
  router.navigate(['/login']);
  return false;
};