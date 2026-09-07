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
      timer: 3000,
      timerProgressBar: true
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
        confirmButton: 'btn btn-tbl-success rounded-pill px-5 py-2 fw-bold shadow-sm',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    });
  }

  public showError(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      icon: 'error',
      confirmButtonText: 'Entendido',
      buttonsStyling: false, 
      customClass: {
        confirmButton: 'btn btn-tbl-close rounded-pill px-5 py-2 fw-bold shadow-sm',
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
        confirmButton: 'btn btn-tbl-close rounded-pill px-5 py-2 fw-bold shadow-sm', 
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    });
  }

  async confirm(title: string, htmlContent: string, confirmButtonText= 'Si, continuar'): Promise<boolean> {
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
        confirmButton: 'btn btn-tbl-success rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    }).then((result) => {
      return result.isConfirmed;
    });
  }

  async confirmDelete(title: string, htmlContent: string, confirmButtonText= 'Si, Borrar'): Promise<boolean> {
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
        confirmButton: 'btn btn-tbl-delete rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    }).then((result) => {
      return result.isConfirmed;
    });
  }

  async confirmClosure(title: string, htmlContent: string, confirmButtonText= 'Si, Cerrar Fase'): Promise<boolean> {
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
        confirmButton: 'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
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
      confirmButtonText: 'Cerrar y Bloquear',
      cancelButtonText: 'Cancelar',
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa morada
        confirmButton: 'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      },
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

  async promptTextInput(title: string, htmlText: string, placeholder: string): Promise<string | null> {
    const result = await Swal.fire({
      title: title,
      html: htmlText,
      icon: 'info', 
      input: 'text',
      inputPlaceholder: placeholder,
      showCancelButton: true,
      confirmButtonText: '<i class="fa-solid fa-file-pdf me-2"></i> Generar Documento',
      cancelButtonText: 'Cancelar',
      buttonsStyling: false,
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa
        confirmButton: 'btn btn-tbl-info rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      },
      inputValidator: (value) => {
        if (!value || value.trim() === '') {
          return 'Debe ingresar un número de requerimiento válido.';
        }
        return null;
      }
    });
    return result.isConfirmed ? result.value : null;
  }

  public showLoading(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
      customClass: {
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold'
      }
    });
  }

  public close(): void {
    Swal.close();
  }

}