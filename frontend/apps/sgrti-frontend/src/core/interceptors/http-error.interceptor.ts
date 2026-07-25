import { HttpInterceptorFn } from '@angular/common/http';
import { catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';
import Swal from 'sweetalert2';


export const httpErrorInterceptor: HttpInterceptorFn = (req, next) => {
  

  return next(req).pipe(
    catchError((error) => {
      let errorMessage = 'Ocurrió un error inesperado.';
      let titleMessage = `Error ${error.status || ''}`; // Título por defecto

      // Evaluamos el tipo de error para personalizar tanto el título como el mensaje
      if (error.status === 422 && error.error?.errors) {
        const firstErrorKey = Object.keys(error.error.errors)[0];
        errorMessage = error.error.errors[firstErrorKey][0];
        titleMessage = 'Error de Validación';
      } else if (error.status === 401) {
        // Personalización específica para la expiración de sesión
        errorMessage = 'Por favor, inicie sesión nuevamente para continuar trabajando.';
        titleMessage = 'Tu sesión expiró'; 
      } else {
        const statusErrors: { [key: number]: string } = {
          400: 'Solicitud incorrecta al servidor.',
          403: 'No tiene permisos para esta acción.',
          404: 'El recurso solicitado no existe.',
          500: 'Error interno del servidor.'
        };
        errorMessage = statusErrors[error.status] || errorMessage;
      }

      Swal.fire({
        icon: 'error',
        title: titleMessage, // Aplicamos la variable dinámica aquí
        text: errorMessage,
        confirmButtonColor: '#d500f9',
        confirmButtonText: 'Aceptar'
      }).then((result) => {
        if (result.isConfirmed && error.status === 401) {
          window.location.href = '/login';
        }
      });

      return throwError(() => error);
    })
  );
};