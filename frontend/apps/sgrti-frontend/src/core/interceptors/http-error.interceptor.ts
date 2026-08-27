import { HttpInterceptorFn } from '@angular/common/http';
import { catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';
import Swal from 'sweetalert2';

export const httpErrorInterceptor: HttpInterceptorFn = (req, next) => {
  
  return next(req).pipe(
    catchError((error) => {
      let errorMessage = 'Ocurrió un error inesperado.';
      let titleMessage = `Error ${error.status || ''}`; 

      // Evaluamos el tipo de error para personalizar los mensajes del interceptor
      if (error.status === 422 && error.error?.errors) {
        const firstErrorKey = Object.keys(error.error.errors)[0];
        errorMessage = error.error.errors[firstErrorKey][0];
        titleMessage = 'Error de Validación';
      } else if (error.status === 401) {
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

      // 🟢 ARQUITECTURA LIMPIA: Lista de errores que delegamos al diseño corporativo (Componentes)
      // 403 = Permisos (manejado por el componente con NotificationService)
      // 422 = Validación (manejado por los formularios reactivos)
      const silentErrors = [403, 422];

      // Solo disparamos SweetAlert si el error NO está en la lista de silenciosos o si es 401 (para desloguear)
      if (!silentErrors.includes(error.status) || error.status === 401) {
        Swal.fire({
          icon: 'error',
          title: titleMessage, 
          text: errorMessage,
          confirmButtonColor: '#d500f9', // Tu color corporativo ECOSGRTI
          confirmButtonText: 'Aceptar'
        }).then((result) => {
          if (result.isConfirmed && error.status === 401) {
            window.location.href = '/login';
          }
        });
      }

      // Dejamos que el error siga su camino hacia el componente
      return throwError(() => error);
    })
  );
};