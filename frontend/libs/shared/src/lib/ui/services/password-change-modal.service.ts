import { Injectable } from '@angular/core';
import Swal from 'sweetalert2';
import { PasswordChangeData } from '@ecosgrti/shared';


@Injectable({
  providedIn: 'root'
})
export class PasswordChangeModalService {

  public async abrirModalCambioPassword(): Promise<PasswordChangeData | null> {
    const result = await Swal.fire({
      title: 'Cambio de Contraseña',
      html: `
        <hr class="my-3">
        <div class="text-start mb-3">
          <p class="text-muted mb-2" style="font-size: 1.1rem;">Tu nueva contraseña debe tener:</p>
          <ul class="text-muted" style="font-size:  1.1rem; padding-left: 1.5rem;">
            <li>Al menos 8 caracteres.</li>
            <li>Al menos una letra mayúscula.</li>
            <li>Al menos una letra minúscula.</li>
            <li>Al menos un número.</li>
            <li>Al menos un signo de puntuación permitido: <strong class="fw-bold text-danger"> . , - _ :</strong></li>
            <li>No puedes repetir tus últimas 2 contraseñas.</li>
          </ul>
        </div>
        <hr class="my-4">
        <div class="text-start mb-4">
          <label for="swal-current-pwd" class="fw-bold mb-1" style="font-size: 1.2rem;">Contraseña Actual</label>
          <div class="position-relative">
            <input type="password" id="swal-current-pwd" class="swal2-input m-0 w-100" style="padding-right: 40px;" placeholder="Ingresa tu contraseña actual">
            <i class="fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3" id="toggle-current-pwd" style="cursor: pointer; color: #6c757d;"></i>
          </div>
        </div>

        <div class="text-start mb-3">
          <label for="swal-new-pwd" class="fw-bold mb-1" style="font-size: 1.2rem;">Nueva Contraseña</label>
          <div class="position-relative">
            <input type="password" id="swal-new-pwd" class="swal2-input m-0 w-100" style="padding-right: 40px;" placeholder="Crea tu nueva contraseña">
            <i class="fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3" id="toggle-new-pwd" style="cursor: pointer; color: #6c757d;"></i>
          </div>
        </div>

        <div class="text-start">
          <label for="swal-confirm-pwd" class="fw-bold mb-1" style="font-size: 1.2rem;">Confirmar Nueva Contraseña</label>
          <div class="position-relative">
            <input type="password" id="swal-confirm-pwd" class="swal2-input m-0 w-100" style="padding-right: 40px;" placeholder="Repite la nueva contraseña">
            <i class="fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3" id="toggle-confirm-pwd" style="cursor: pointer; color: #6c757d;"></i>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Actualizar Contraseña',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#0d6efd',
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa morada
        confirmButton: 'btn btn-primary rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        cancelButton: 'btn btn-outline-secondary rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold '
      },
      didOpen: () => {
        const setupToggle = (inputId: string, iconId: string) => {
          const input = document.getElementById(inputId) as HTMLInputElement;
          const icon = document.getElementById(iconId);
          if (icon && input) {
            icon.addEventListener('click', () => {
              const isPassword = input.type === 'password';
              input.type = isPassword ? 'text' : 'password';
              icon.className = isPassword 
                ? 'fas fa-eye-slash position-absolute top-50 end-0 translate-middle-y me-3' 
                : 'fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3';
            });
          }
        };

        setupToggle('swal-current-pwd', 'toggle-current-pwd');
        setupToggle('swal-new-pwd', 'toggle-new-pwd');
        setupToggle('swal-confirm-pwd', 'toggle-confirm-pwd');
      },
      preConfirm: () => {
        const current = (document.getElementById('swal-current-pwd') as HTMLInputElement).value;
        const newPwd = (document.getElementById('swal-new-pwd') as HTMLInputElement).value;
        const confirm = (document.getElementById('swal-confirm-pwd') as HTMLInputElement).value;

        if (!current) {
          Swal.showValidationMessage('Debes ingresar tu contraseña actual.');
          return false;
        }
        if (newPwd.length < 8) {
          Swal.showValidationMessage('La nueva contraseña debe tener al menos 8 caracteres.');
          return false;
        }
        if (!/[A-Z]/.test(newPwd)) {
          Swal.showValidationMessage('La nueva contraseña debe incluir al menos una letra mayúscula.');
          return false;
        }
        if (!/[a-z]/.test(newPwd)) {
          Swal.showValidationMessage('La nueva contraseña debe incluir al menos una letra minúscula.');
          return false;
        }
        if (!/\d/.test(newPwd)) {
          Swal.showValidationMessage('La nueva contraseña debe incluir al menos un número.');
          return false;
        }
        if (!/[.,\-_:]/.test(newPwd)) {
          Swal.showValidationMessage('La contraseña debe incluir un signo de puntuación válido ( . , - _ : ).');
          return false;
        }
        if (newPwd !== confirm) {
          Swal.showValidationMessage('La confirmación no coincide con la nueva contraseña.');
          return false;
        }

        return { current_password: current, new_password: newPwd };
      }
    });

    if (result.isConfirmed && result.value) {
      return result.value;
    }
    return null;
  }
}