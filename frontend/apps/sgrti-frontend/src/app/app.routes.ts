import { Routes } from '@angular/router';
// Importa la lógica por la puerta de datos
import { authGuard } from '@ecosgrti/security/data-access';
// Importa el componente de forma estática (tradicional)
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
    
    loadComponent: () => import('./features/dashboard/dashboard.component').then(m => m.DashboardComponent),
    title: 'Dashboard | ECOSGRTI',
    canActivate: [authGuard],
  },
  
  {
    path: '**',
    redirectTo: 'login'
  },
];