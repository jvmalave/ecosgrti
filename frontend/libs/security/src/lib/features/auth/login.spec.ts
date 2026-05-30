import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { By } from '@angular/platform-browser';
import { LoginComponent } from './login';
import { throwError } from 'rxjs';
import { vi, Mock} from 'vitest'; // 🔥 Importamos Vitest explícitamente


// NOTA: Ajusta estas rutas a donde realmente vivan tus servicios
import { AuthService } from '@ecosgrti/security/data-access'; 
import { AlertService } from '@ecosgrti/security/data-access'; 

describe('US21: Interfaz de Autenticación y UX (LoginComponent)', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  
  // Creamos espías para interceptar las llamadas a los servicios sin tocar el backend real
  let alertServiceSpy: { error: Mock };
  let authServiceSpy: { login: Mock };

  beforeEach(async () => {
    // La asignación se mantiene igual
    alertServiceSpy = { error: vi.fn() };
    authServiceSpy = { login: vi.fn() };
    await TestBed.configureTestingModule({
      imports: [LoginComponent,ReactiveFormsModule],
      providers: [
        { provide: AlertService, useValue: alertServiceSpy },
        { provide: AuthService, useValue: authServiceSpy }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('Escenario 01: Validaciones reactivas del formulario en tiempo real', () => {
    // GIVEN: Seleccionamos los elementos del DOM
    const passwordInput = fixture.debugElement.query(By.css('input[formControlName="password"]')).nativeElement;
    const submitButton = fixture.debugElement.query(By.css('button[type="submit"]')).nativeElement;

    // WHEN: Interacción del usuario
    passwordInput.dispatchEvent(new Event('focus'));
    passwordInput.value = '';
    passwordInput.dispatchEvent(new Event('input'));
    passwordInput.dispatchEvent(new Event('blur'));
    
    fixture.detectChanges();

    // THEN: Validaciones
    expect(passwordInput.classList).toContain('is-invalid');

    const errorMessage = fixture.debugElement.query(By.css('.invalid-feedback'));
    expect(errorMessage).toBeTruthy();
    expect(errorMessage.nativeElement.textContent).toContain('La contraseña es requerida (mín. 6 caracteres');

    expect(submitButton.disabled).toBe(true);
  });

  it('Escenario 02: Intercepción de error y despliegue de SweetAlert2', () => {
    // GIVEN: Credenciales
    component.loginForm.controls['username'].setValue('admin@ecosgrti.com');
    component.loginForm.controls['password'].setValue('clave_erronea');
    
    // Simulamos respuesta 401 usando la API de Vitest (mockReturnValue)
    authServiceSpy.login.mockReturnValue(throwError(() => ({ status: 401 })));

    // WHEN: Se envía el formulario
    component.onSubmit();

    // THEN: Se llama a SweetAlert2
    expect(alertServiceSpy.error).toHaveBeenCalled();

    // Capturamos los argumentos de Vitest y validamos
    
    // THEN: Se llama a SweetAlert2 a través del servicio
    expect(alertServiceSpy.error).toHaveBeenCalled();

    // Capturamos el mensaje que el componente le envió al servicio
    const alertMessage = alertServiceSpy.error.mock.calls[0][0];
    
    // Validamos que el mensaje contenga el texto esperado
    expect(alertMessage).toContain('Credenciales inválidas');
  });
});
