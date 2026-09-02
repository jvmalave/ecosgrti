import { Injectable } from '@angular/core';
import Swal from 'sweetalert2';
// import { INotificationService } from '@ecosgrti/shared/interfaces';

@Injectable({
  providedIn: 'root'
})
export class NotificationService  {

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


  public showSuccess(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message, 
      icon: 'success',
      confirmButtonText: 'OK',
      buttonsStyling: false, 
      customClass: {
        confirmButton: 'btn btn-secondary rounded-pill px-5 py-2 fw-bold shadow-sm',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    });
  }

  /**
   * Alerta de Error con estilo corporativo (Opcional, pero recomendado)
   */
  public showError(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      icon: 'error',
      confirmButtonText: 'Entendido',
      buttonsStyling: false, 
      customClass: {
        confirmButton: 'btn btn-secondary rounded-pill px-5 py-2 fw-bold shadow-sm',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    });
  }


  public showWarning(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      icon: 'warning',
      confirmButtonText: 'Entendido',
      buttonsStyling: false, 
      customClass: {
        confirmButton: 'btn btn-secondary rounded-pill px-5 py-2 fw-bold shadow-sm', 
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    });
  }


  

  // showError(title: string, message: string): void {
  //   Swal.fire(title, message, 'error');
  // }

  // showSuccess(title: string, message: string): void {
  //   Swal.fire(title, message, 'success');
  // }

  // showWarning(title: string, message: string): void {
  //   Swal.fire(title, message, 'warning');
  // }


  async confirm(title: string, htmlContent: string, confirmButtonText= 'Sí, continuar'): Promise<boolean> {
    return Swal.fire({
      title: title,
      html: htmlContent, 
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: confirmButtonText,
      cancelButtonText: 'Cancelar',
      buttonsStyling: false, 
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa morada
        confirmButton: 'btn btn-success rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        cancelButton: 'btn btn-outline-secondary rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    }).then((result) => {
      return result.isConfirmed;
    });
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
      confirmButtonColor: '#198754',
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