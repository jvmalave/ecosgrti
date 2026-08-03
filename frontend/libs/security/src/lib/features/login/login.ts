import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms'; // Importar las herramientas de formularios
import { AuthService} from '../../data-access/services/auth.service'
import { Router } from '@angular/router';
import { UserSession } from '../../data-access/models/auth.model';
import { AlertService } from '../../data-access/services/alert.service';

@Component({
  selector: 'lib-login',
  standalone: true,
  imports: [
    CommonModule, 
    ReactiveFormsModule // 🔌 Activar los formularios reactivos en este componente standalone
  ],
  templateUrl: './login.html',
  styleUrl: './login.scss',
  changeDetection: ChangeDetectionStrategy.OnPush, //  Optimiza el rendimiento nativa de Angular
})
export class LoginComponent {
  //  Inyectar el constructor de formularios de forma funcional
  private readonly fb = inject(NonNullableFormBuilder);

  private readonly authService = inject(AuthService);

  private readonly alertService = inject(AlertService);

  public readonly errorMessage = signal<string | null>(null);

  private readonly router = inject(Router);

  //  Definir la estructura del formulario con sus validaciones básicas
  public readonly loginForm = this.fb.group({
    username: ['', [Validators.required, Validators.minLength(3)]],
    password: ['', [Validators.required, Validators.minLength(6)]],
  });

  /**
   * Método que se ejecutará cuando el usuario presione el botón de ingresar
   */
  public onSubmit(): void {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched(); // Marca los campos para mostrar los errores visuales
      return;
    }

    // 1. Capturar los datos limpios del formulario reactivo
    const credentials = this.loginForm.getRawValue();

    // 2. Creamos el objeto con la estructura exacta que la API necesita 
    const apiPayload = {
      email: credentials.username, // Traducimos 'username' a 'email' para el backend
      password: credentials.password
    };
    
    console.log('Datos mapeados listos para enviar al backend:', apiPayload);

    // 3. Conectar con el servicio mandando el objeto 'apiPayload'
    this.authService.login(apiPayload).subscribe({
      next: (response: UserSession) => {
        console.log('¡Éxito! Sesión iniciada', response);
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        console.error('Error en la autenticación', err);
        this.alertService.error('Credenciales inválidas. Por favor, verifica tu usuario y contraseña.');
      }
    });
  }
}

