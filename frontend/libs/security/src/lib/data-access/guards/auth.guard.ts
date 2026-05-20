import { inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common'; 
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const authGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);
  const platformId = inject(PLATFORM_ID); 

  const session = authService.currentUser();

  // 1. Si ya hay sesión en memoria, pasa.
  if (session && session.token) {
    return true;
  }

  // 2. 🛡️ LA PIEZA CLAVE SSR: 
  // Si estamos en el servidor (Node), lo dejamos pasar porque no puede verificar el token.
  if (!isPlatformBrowser(platformId)) {
    return true; 
  }

  // 3. Si ya estamos en el navegador, revisamos el almacenamiento físico
  const savedUser = localStorage.getItem('ecosgrti_session');
  if (savedUser) {
    const parsed = JSON.parse(savedUser);
    if (parsed && parsed.token) {
      return true; 
    }
  }

  // 4. Si estamos en el navegador y realmente no hay nada, entonces sí rebotamos al login
  return router.createUrlTree(['/login']);
};