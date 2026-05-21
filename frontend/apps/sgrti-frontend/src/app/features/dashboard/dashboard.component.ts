import { Component, inject } from '@angular/core';
import { AuthService } from '@ecosgrti/security/data-access'
import { Router } from '@angular/router';
import { UpperCasePipe } from '@angular/common';


@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [UpperCasePipe],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss'
})
export class DashboardComponent {
  //  Se Inyecta el servicio de autenticación
  public authService = inject(AuthService);
  private router = inject(Router);

  //  Se obtiene el usuario actual desde el Signal (es de solo lectura)
  public user = this.authService.currentUser;
  

  /**
   *Se Finaliza la sesión del usuario
   */
  logout() {
    this.authService.logout();
  }
}