import { Injectable } from '@angular/core';
import Swal, { SweetAlertIcon } from 'sweetalert2';

@Injectable({ 
  providedIn: 'root' 
})
export class AlertService {
  
  // Método base configurable
  public show(title: string, text: string, icon: SweetAlertIcon = 'info') {
    Swal.fire({
      title,
      text,
      icon,
      confirmButtonColor: '#e91e63', // 🎨 Nuestro Magenta de Cantv
      confirmButtonText: 'Entendido',
      customClass: {
        popup: 'rounded-4' // Usamos bordes redondeados para coincidir con nuestro SCSS
      }
    });
  }

  // Atajos rápidos
  public error(text: string, title = '¡Acceso Denegado!') {
    this.show(title, text, 'error');
  }

  public success(text: string, title = '¡Éxito!') {
    this.show(title, text, 'success');
  }
}