import { TestBed } from '@angular/core/testing';
import { Router, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { authGuard } from './auth.guard';
import { AuthService } from '../services/auth.service';
import { vi, Mock } from 'vitest';

describe('US22: Protección de Rutas (authGuard)', () => {
  let routerSpy: { navigate: Mock };
  let authServiceSpy: { currentSession: Mock };

  beforeEach(() => {
    // 1. Preparamos espías ligeros
    routerSpy = { navigate: vi.fn() };
    authServiceSpy = { currentSession: vi.fn() };

    // 2. Configuramos el entorno de pruebas inyectando nuestros espías
    TestBed.configureTestingModule({
      providers: [
        { provide: Router, useValue: routerSpy },
        { provide: AuthService, useValue: authServiceSpy }
      ]
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('Escenario 04.1: Permite el acceso si el usuario está autenticado', () => {
    // GIVEN: Una sesión activa en el Signal
    authServiceSpy.currentSession.mockReturnValue({ token: 'jwt-super-secreto' });

    // WHEN: Se evalúa la función del Guardián
    const result = TestBed.runInInjectionContext(() => 
      authGuard({} as unknown as ActivatedRouteSnapshot, {} as unknown as RouterStateSnapshot)
    );

    // THEN: El acceso es permitido
    expect(result).toBe(true);
    // AND: No hay ninguna redirección
    expect(routerSpy.navigate).not.toHaveBeenCalled();
  });

  it('Escenario 04.2: Bloquea y redirige al login si NO hay sesión', () => {
    // GIVEN: Un usuario sin sesión (null)
    authServiceSpy.currentSession.mockReturnValue(null);

    // WHEN: Se evalúa la función del Guardián
    const result = TestBed.runInInjectionContext(() => 
      authGuard({} as unknown as ActivatedRouteSnapshot, {} as unknown as RouterStateSnapshot)
    );

    // THEN: El acceso es denegado
    expect(result).toBe(false);
    // AND: El portero lo manda directo al login
    expect(routerSpy.navigate).toHaveBeenCalledWith(['/login']);
  });
});