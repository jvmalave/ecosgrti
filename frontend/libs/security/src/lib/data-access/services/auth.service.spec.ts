import { TestBed } from '@angular/core/testing';

import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Router } from '@angular/router';
import { AuthService } from './auth.service';
import { vi, Mock } from 'vitest';
import { AUTH_API_URL } from '../tokens/tokens';




describe('US22: Gestión de Estado Reactivo y Persistencia (AuthService)', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;
  let routerSpy: { navigate: Mock };

  beforeEach(() => {
    // 1. Preparamos los espías
    routerSpy = { navigate: vi.fn() };

    // 2. Limpiamos el entorno antes de cada prueba
    localStorage.clear();
    vi.restoreAllMocks();

    // 3. Configuramos el módulo de pruebas
    TestBed.configureTestingModule({
      providers: [
        AuthService,
        { provide: Router, useValue: routerSpy },
        { provide: AUTH_API_URL, useValue: 'http://localhost:8000/api/auth' },
        provideHttpClient(),
        provideHttpClientTesting()
      ]
    });
  });

  afterEach(() => {
    // Verificamos que no queden peticiones HTTP colgadas
    if (httpMock) {
      httpMock.verify();
    }
  });

  it('Escenario 01: Rehidratación de la sesión al recargar la página', () => {
    // GIVEN: Un token guardado previamente en el navegador
    const fakeSession = { token: 'token-jwt-123', user: { name: 'Admin', avatar: 'admin.png' } };
    localStorage.setItem('ecosgrti_session', JSON.stringify(fakeSession));

    // WHEN: El servicio se inicializa (simulando una recarga de página)
    service = TestBed.inject(AuthService);
    
    // THEN: El Signal "currentSession" debe leer el localStorage y actualizarse
    const session = service.currentSession();
    expect(session).toBeTruthy();
    expect(session?.token).toBe('token-jwt-123');
  });

  it('Escenario 03: Sincronización del Logout y limpieza integral', () => {
    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
    

    localStorage.setItem('ecosgrti_session', 'datos-falsos-de-prueba');

    // GIVEN: Un usuario que ejecuta la acción de salir
    service.logout().subscribe();

    const injectedUrl = TestBed.inject(AUTH_API_URL);

    // THEN: Se envía la petición a Laravel
    const req = httpMock.expectOne(`${injectedUrl}/logout`);
    expect(req.request.method).toBe('POST');
    req.flush({}); 

    // AND: Se limpia el Signal (queda nulo o indefinido)
    expect(service.currentSession()).toBeNull();

    expect(localStorage.getItem('ecosgrti_session')).toBeNull();

    
  });
});