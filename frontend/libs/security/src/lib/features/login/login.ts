import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms'; 
import { AuthService} from '../../data-access/services/auth.service'
import { Router } from '@angular/router';
import { UserSession } from '../../data-access/models/auth.model';
import { AlertService } from '../../data-access/services/alert.service';

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

  public readonly loginForm = this.fb.group({
    username: ['', [Validators.required, Validators.minLength(3)]],
    password: ['', [Validators.required, Validators.minLength(6)]],
  });

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