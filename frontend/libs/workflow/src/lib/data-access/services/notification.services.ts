import { Injectable } from '@angular/core';
import Swal from 'sweetalert2';
import { INotificationService } from '@ecosgrti/shared/interfaces';

@Injectable({
  providedIn: 'root'
})
export class NotificationService implements INotificationService {

  toastSuccess(message: string): void {
    Swal.fire({
      icon: 'success',
      title: message,
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
  }

  showError(title: string, message: string): void {
    Swal.fire(title, message, 'error');
  }

  showSuccess(title: string, message: string): void {
    Swal.fire(title, message, 'success');
  }

  showWarning(title: string, message: string): void {
    Swal.fire(title, message, 'warning');
  }

  async confirm(title: string, text: string): Promise<boolean> {
    const result = await Swal.fire({
      title,
      text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, continuar',
      cancelButtonText: 'Cancelar'
    });
    return result.isConfirmed;
  }

  async promptText(title: string, htmlText: string, placeholder: string): Promise<string | null> {
    const result = await Swal.fire({
      title: title,
      html: htmlText,
      icon: 'warning',
      input: 'textarea',
      inputPlaceholder: placeholder,
      inputAttributes: {
        'aria-label': placeholder
      },
      showCancelButton: true,
      confirmButtonColor: '#8e1482',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Cerrar y Bloquear',
      cancelButtonText: 'Cancelar',
      preConfirm: (text) => {
        if (!text || text.trim().length < 10) {
          Swal.showValidationMessage('Debe ingresar una justificación de al menos 10 caracteres.');
          return false;
        }
        return text.trim();
      }
    });
    return result.isConfirmed ? result.value : null;
  }

}