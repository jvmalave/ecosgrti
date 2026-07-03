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
    title: 'Dashboard | ECOSGRTI',
    canActivate: [authGuard],
  },
  {
    path: 'requerimientos/crear', 
    loadComponent: () => import('@sgrti/core').then(m => m.RequirementCreateComponent),
    title: 'Crear Requerimiento | ECOSGRTI',
    canActivate: [authGuard], 
  },
  {
    path: 'requerimientos/:requirementId/estimacion', 
    loadComponent: () => import('@sgrti/core').then(m => m.EstimationFormComponent),
    title: 'Estimación | ECOSGRTI',
    canActivate: [authGuard], 
  },
  {
    path: '**',
    redirectTo: 'login'
  },
];