// import { inject } from '@angular/core';
// import { CanActivateFn, Router } from '@angular/router';
// import { AuthService } from '../services/auth.service';

// export const authGuard: CanActivateFn = () => {
//   const authService = inject(AuthService);
//   const router = inject(Router);

//   console.log('--- [Guard Ejecutándose] ---');
//   console.log('Valor actual del Signal en el Guard:', authService.currentUser());
//   console.log('Valor directo en localStorage:', localStorage.getItem('ecosgrti_session'));

//   // 1. Leemos el Signal de la sesión actual 
//   const session = authService.currentUser();

//   // 2. Si hay un token válido, permitimos el acceso a la ruta 
//   if (session && session.token) {
//     return true;
//   }

//   // 3. Si no está autenticado, lo mandamos al login y bloqueamos el acceso 
//   return router.createUrlTree(['/login']);
// };

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