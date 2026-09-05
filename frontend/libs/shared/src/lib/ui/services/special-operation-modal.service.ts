import { Injectable } from '@angular/core';
import Swal from 'sweetalert2';

@Injectable({
  providedIn: 'root'
})
export class SpecialOperationModalService {

  // Devuelve una Promesa con los datos si es exitoso, o null si el usuario cancela
  public async abrirModalDeConfiguracionPin(): Promise<{password: string, pin: string} | null> {
    const result = await Swal.fire({
      title: 'Configuración de Seguridad',
      html: `
        <p class="text-muted mb-3" style="font-size: 0.9em;">
          Verifica tu identidad y define un PIN numérico (4 a 6 dígitos) para autorizar operaciones críticas.
        </p>
        
        <div class="text-start mb-3">
          <label for="swal-login-password" class="fw-bold mb-1" style="font-size: 0.9em;">Contraseña de Inicio de Sesión</label>
          <div class="position-relative">
            <input type="password" id="swal-login-password" class="swal2-input m-0 w-100" style="padding-right: 40px;" placeholder="Tu contraseña actual">
            <i class="fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3" id="toggle-pwd" style="cursor: pointer; color: #6c757d;"></i>
          </div>
        </div>

        <div class="text-start mb-3">
          <label for="swal-new-pin" class="fw-bold mb-1" style="font-size: 0.9em;">Nuevo PIN de Operaciones</label>
          <div class="position-relative">
            <input type="password" id="swal-new-pin" class="swal2-input m-0 w-100" style="padding-right: 40px;" placeholder="Ej. 123456" maxlength="6" inputmode="numeric">
            <i class="fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3" id="toggle-pin" style="cursor: pointer; color: #6c757d;"></i>
          </div>
        </div>

        <div class="text-start">
          <label for="swal-confirm-pin" class="fw-bold mb-1" style="font-size: 0.9em;">Confirmar Nuevo PIN</label>
          <div class="position-relative">
            <input type="password" id="swal-confirm-pin" class="swal2-input m-0 w-100" style="padding-right: 40px;" placeholder="Repite el PIN" maxlength="6" inputmode="numeric">
            <i class="fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3" id="toggle-confirm-pin" style="cursor: pointer; color: #6c757d;"></i>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Guardar PIN',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#0d6efd',
      customClass: { popup: 'rounded-4' },
      didOpen: () => {
        const setupToggle = (inputId: string, iconId: string) => {
          const input = document.getElementById(inputId) as HTMLInputElement;
          const icon = document.getElementById(iconId);
          if (icon && input) {
            icon.addEventListener('click', () => {
              const isPassword = input.type === 'password';
              input.type = isPassword ? 'text' : 'password';
              icon.className = isPassword ? 'fas fa-eye-slash position-absolute top-50 end-0 translate-middle-y me-3' : 'fas fa-eye position-absolute top-50 end-0 translate-middle-y me-3';
            });
          }
        };

        setupToggle('swal-login-password', 'toggle-pwd');
        setupToggle('swal-new-pin', 'toggle-pin');
        setupToggle('swal-confirm-pin', 'toggle-confirm-pin');
      },
      preConfirm: () => {
        const password = (document.getElementById('swal-login-password') as HTMLInputElement).value;
        const pin = (document.getElementById('swal-new-pin') as HTMLInputElement).value;
        const confirmPin = (document.getElementById('swal-confirm-pin') as HTMLInputElement).value;

        if (!password) {
          Swal.showValidationMessage('Debes ingresar tu contraseña actual.');
          return false;
        }
        if (!pin || pin.length < 4 || pin.length > 6 || !/^\d+$/.test(pin)) {
          Swal.showValidationMessage('El PIN debe contener entre 4 y 6 números.');
          return false;
        }
        if (pin !== confirmPin) {
          Swal.showValidationMessage('Los PINs ingresados no coinciden.');
          return false;
        }

        return { password, pin };
      }
    });

    if (result.isConfirmed && result.value) {
      return result.value; // Retorna { password, pin } al componente
    }
    
    return null; // Si cerró el modal o canceló
  }
}