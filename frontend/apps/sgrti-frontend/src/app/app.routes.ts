import { Routes } from '@angular/router';
// 1. Importamos la lógica por la puerta de datos
import { authGuard } from '@ecosgrti/security/data-access';
// 2. Importamos el componente de forma estática (tradicional)
import { LoginComponent } from '@ecosgrti/security'; 


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
    loadComponent: () => import('@sgrti/core').then((m) => m.DashboardComponent),
    canActivate: [authGuard],
  },
  {
    path: 'requerimientos/crear', 
    // Usamos Lazy Loading apuntando a la ruta física de tu componente en la librería core
    loadComponent: () => 
      import('@sgrti/core')
      .then(m => m.RequirementCreateComponent),
  },

  {
    path: '**',
    redirectTo: 'login'
  },
];