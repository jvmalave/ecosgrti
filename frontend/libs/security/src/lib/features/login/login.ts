import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms'; 
import { AuthService} from '../../data-access/services/auth.service'
import { Router } from '@angular/router';
import { UserSession } from '../../data-access/models/auth.model';
import { AlertService } from '../../data-access/services/alert.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'lib-login',
  standalone: true,
  imports: [
    CommonModule, 
    ReactiveFormsModule 
  ],
  templateUrl: './login.html',
  styleUrl: './login.scss',
  changeDetection: ChangeDetectionStrategy.OnPush, 
})
export class LoginComponent {
  
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly authService = inject(AuthService);
  private readonly alertService = inject(AlertService);
  private readonly router = inject(Router);

  public readonly errorMessage = signal<string | null>(null);

  public readonly showPassword = signal<boolean>(false);

  public readonly loginForm = this.fb.group({
    username: ['', [Validators.required, Validators.minLength(5)]],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });

  
  public togglePasswordVisibility(): void {
    this.showPassword.update((value) => !value);
  }

  public mostrarAyudaPassword(): void {
    Swal.fire({
      icon: 'info',
      title: '¿Problemas con tu contraseña?',
      html: `
        <div class="text-start text-muted ps-4 mt-4" style="font-size: 1.3rem;">
          <p>
            <i class="fa-solid fa-shield-halved me-2 text-warning"></i>Por políticas de seguridad, el restablecimiento de credenciales olvidadas o cuentas bloqueadas se gestiona estrictamente a través del <strong>Administrador del Sistema</strong>.
          </p>
          <p class="mt-4">
            <i class="fa-solid fa-phone me-2 text-dark"></i>Por favor, comunícate con tu supervisor para que solicite el restablecimiento de tu cuenta y te asignen una nueva contraseña temporal de ingreso.
          </p>
        </div>
      `,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#0d6efd',
      customClass: { 
        confirmButton: 'btn btn-primary rounded-pill px-4 mx-2 fw-bold shadow-sm', 
        popup: 'rounded-4 border-top border-4 border-brand w-50',
        title: 'fs-4 text-dark fw-bold '
      }
    });
  }

  public onSubmit(): void {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched(); 
      return;
    }

    const credentials = this.loginForm.getRawValue();

    // Envia exactamente 'username' al backend de Laravel
    const apiPayload = {
      username: credentials.username, 
      password: credentials.password
    };
    
    //console.log('Datos mapeados listos para enviar al backend:', apiPayload);

    this.authService.login(apiPayload).subscribe({
      next: (response: UserSession) => {
        console.log('¡Éxito! Sesión iniciada', response.username, response.roles);
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        console.error('Error en la autenticación', err);
        this.alertService.error('Credenciales inválidas. Por favor, verifica tu usuario y contraseña.');
      }
    });



  }
}