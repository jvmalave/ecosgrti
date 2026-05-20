import { Routes } from '@angular/router';
import { authGuard } from '@ecosgrti/security';


export const appRoutes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'login', //  Si entran a la raíz, los mandamos al login automáticamente
  },
  {
    path: 'login',
    // Cargamos el componente de forma directa desde nuestra librería de seguridad
    loadComponent: () => import('@ecosgrti/security').then((m) => m.LoginComponent),
  },
  {
    path: 'dashboard',
    // 🚀 Lazy Loading para el futuro componente Dashboard (evita cargar código innecesario al inicio)
    loadComponent: () => import('./features/dashboard/dashboard.component').then((m) => m.DashboardComponent),
    canActivate: [authGuard],
  },
  {
    path: '**',
    redirectTo: 'login', // ↩️ Cualquier ruta extraña o inexistente rebota al login
  },
];