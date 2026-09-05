import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, switchMap } from 'rxjs/operators';
import { throwError, from, EMPTY } from 'rxjs';
import Swal from 'sweetalert2';

// Importaciones de tus librerías Nx
import { PasswordChangeModalService } from '@ecosgrti/shared';

import { AuthService } from '@ecosgrti/security'; 

// Bandera global para evitar que múltiples peticiones simultáneas abran el modal varias veces
let isChangingPassword = false;

export const httpErrorInterceptor: HttpInterceptorFn = (req, next) => {
  const passwordModalService = inject(PasswordChangeModalService);
  const authService = inject(AuthService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      
      // ========================================================================
      // NUEVA LÓGICA: Intercepción del 426 (Caducidad de Contraseña)
      // ========================================================================
      if (error.status === 426) {
        if (!isChangingPassword) {
          isChangingPassword = true;
          
          return from(passwordModalService.abrirModalCambioPassword()).pipe(
            switchMap((data) => {
              if (data) {
                // El usuario llenó el formulario y presionó "Actualizar"
                Swal.fire({
                  title: 'Procesando...',
                  text: 'Validando políticas de seguridad',
                  allowOutsideClick: false,
                  didOpen: () => { Swal.showLoading(); }
                });

                // Enviamos los datos al backend
                return authService.changePassword(data).pipe(
                  switchMap((res) => {
                    isChangingPassword = false;
                    
                    Swal.fire({
                      title: '¡Cambio de contraseña exitoso!',
                      text: res.message,
                      icon: 'success',
                      confirmButtonText: 'Entendido',
                      customClass: {
                        confirmButton: 'btn btn-primary rounded-pill px-4 mx-2 fw-bold shadow-sm',
                        popup: 'rounded-4 border-top border-4 border-brand'}
                    }).then(() => {
                      // Recargamos la aplicación para limpiar estado y reanudar operaciones
                      window.location.reload(); 
                    });
                    
                    return EMPTY; // Detenemos la propagación del error
                  }),
                  catchError((err: HttpErrorResponse) => {
                    isChangingPassword = false;
                    const errorMessage = err.error?.message || 'No se pudo actualizar la contraseña.';
                    Swal.fire('Error de Seguridad', errorMessage, 'error');
                    
                    return throwError(() => err);
                  })
                );
              } else {
                // Si cancela, liberamos la bandera y propagamos el error original
                isChangingPassword = false;
                return throwError(() => error);
              }
            })
          );
        }
        // Si ya se está procesando un cambio, silenciamos las peticiones paralelas rechazadas
        return EMPTY; 
      }

      // ========================================================================
      // LÓGICA EXISTENTE: Manejo estándar de errores (401, 403, 422, 500)
      // ========================================================================
      let errorMessage = 'Ocurrió un error inesperado.';
      let titleMessage = `Error ${error.status || ''}`; 

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

      // 403 = Permisos (manejado por componentes), 422 = Validación (manejado por formularios)
      const silentErrors = [403, 422];

      if (!silentErrors.includes(error.status) || error.status === 401) {
        Swal.fire({
          icon: 'error',
          title: titleMessage, 
          text: errorMessage,
          confirmButtonColor: '#0d6efd',
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