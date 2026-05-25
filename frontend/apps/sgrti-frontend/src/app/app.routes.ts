import { Routes } from '@angular/router';
// 1. Importamos la lógica por la puerta de datos
import { authGuard } from '@ecosgrti/security/data-access';
// 2. Importamos el componente de forma estática (tradicional)
import { LoginComponent } from './features/auth/login'; 

export const appRoutes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'login',
  },
  {
    path: 'login',
    component: LoginComponent, 
  },
  {
    path: 'dashboard',
    // 🚀 El Dashboard SÍ se queda con lazy loading, porque no todos llegan aquí
    loadComponent: () => import('./features/dashboard/dashboard.component').then((m) => m.DashboardComponent),
    canActivate: [authGuard],
  },
  {
    path: '**',
    redirectTo: 'login',
  },
];