import { TestBed } from '@angular/core/testing';
import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from '../services/auth.service';
import { vi, Mock } from 'vitest';

describe('US22: Adjuntar Token JWT (authInterceptor)', () => {
  let httpClient: HttpClient;
  let httpMock: HttpTestingController;
  let authServiceSpy: { currentSession: Mock };

  beforeEach(() => {
    // 1. Creamos el espía para aislar el AuthService
    authServiceSpy = { currentSession: vi.fn() };

    // 2. Configuramos el entorno de pruebas registrando el interceptor de forma funcional
    TestBed.configureTestingModule({
      providers: [
        { provide: AuthService, useValue: authServiceSpy },
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting()
      ]
    });

    httpClient = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
    vi.restoreAllMocks();
  });

  it('Escenario 02.1: Inyecta la cabecera Authorization Bearer si el usuario tiene un token', () => {
    // GIVEN: Una sesión activa con un token válido
    authServiceSpy.currentSession.mockReturnValue({ token: 'jwt-token-valido' });

    // WHEN: Se realiza cualquier petición HTTP en la app
    httpClient.get('/api/usuarios').subscribe();

    // THEN: El interceptor actúa y añade el token Bearer
    const req = httpMock.expectOne('/api/usuarios');
    expect(req.request.headers.has('Authorization')).toBe(true);
    expect(req.request.headers.get('Authorization')).toBe('Bearer jwt-token-valido');
    req.flush([]);
  });

  it('Escenario 02.2: No altera la petición HTTP si el usuario NO está autenticado', () => {
    // GIVEN: No hay sesión activa (usuario no logueado)
    authServiceSpy.currentSession.mockReturnValue(null);

    // WHEN: Se realiza una petición HTTP (como el login)
    httpClient.get('/api/auth/login').subscribe();

    // THEN: La petición pasa limpia sin cabeceras extras de autorización
    const req = httpMock.expectOne('/api/auth/login');
    expect(req.request.headers.has('Authorization')).toBe(false);
    req.flush([]);
  });
});