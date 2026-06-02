import { describe, it, expect, beforeEach, vi } from 'vitest';
import { ComponentFixture, TestBed, fakeAsync, tick } from '@angular/core/testing';
import { DashboardComponent } from './dashboard.component';
import { RequirementService } from '../../data-access/services/requirement.service';
import { AuthService } from '@ecosgrti/security/data-access';
import { of } from 'rxjs';
import { signal } from '@angular/core';

describe('DashboardComponent (US05 - BDD Scenarios)', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;
  
  // Mocks de nuestros servicios
  let mockRequirementService: { getDashboardRequirements: ReturnType<typeof vi.fn> };
  let mockAuthService: { currentUser: unknown, logout: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    // 1. Configuramos el Espía (Spy) del RequirementService
    mockRequirementService = {
      getDashboardRequirements: vi.fn().mockReturnValue(of({
        message: 'Éxito',
        data: [{ rrti: 'RRTI-2026-001', requirement_type: 'Test' }],
        meta: { has_more: false }
      }))
    };

    // 2. Configuramos el Espía del AuthService (simulando un usuario logueado)
    mockAuthService = {
      currentUser: signal({ id: '123', name: 'Coordinador CSPE' }),
      logout: vi.fn().mockReturnValue(of(true))
    };

    // 3. Limpiamos el LocalStorage antes de cada prueba para no contaminarlas
    localStorage.clear();
    
    await TestBed.configureTestingModule({
      imports: [DashboardComponent], // Al ser standalone lo importamos aquí
      providers: [
        { provide: RequirementService, useValue: mockRequirementService },
        { provide: AuthService, useValue: mockAuthService },
        // Simulamos el router en caso de que lo necesitemos para el logout
        { provide: 'Router', useValue: { navigate: vi.fn() } }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
  });

  // ========================================================================
  // Criterio T05.2: Carga Inicial (Simulada para Frontend)
  // ========================================================================
  it('Escenario: Carga inicial de requerimientos (Dashboard)', () => {
    // Cuando el componente se inicializa
    fixture.detectChanges(); // Esto dispara ngOnInit

    // Entonces el servicio debe ser llamado inmediatamente (buscando estado 'active')
    expect(mockRequirementService.getDashboardRequirements).toHaveBeenCalledWith('active', 10, 0, undefined);
    
    // Y los datos deben cargarse en el Signal
    expect(component.requirements().length).toBe(1);
    expect(component.requirements()[0].rrti).toBe('RRTI-2026-001');
  });

  // ========================================================================
  // Criterio T05.1: Buscador Reactivo y Paginación (Debounce & Distinct)
  // ========================================================================
  it('Escenario: Búsqueda reactiva optimizada por código RRTI', fakeAsync(() => {
    // Dado que el componente está inicializado
    fixture.detectChanges();
    
    // Limpiamos el contador de llamadas iniciales para probar solo la búsqueda
    mockRequirementService.getDashboardRequirements.mockClear();

    // Cuando el usuario escribe algo en el buscador
    component.searchControl.setValue('RRTI-2026');
    
    // Si no esperamos el debounceTime, no debería haber llamado al API aún
    expect(mockRequirementService.getDashboardRequirements).not.toHaveBeenCalled();

    // Dejamos pasar el tiempo exacto del Debounce (500ms)
    tick(500);

    // Entonces:
    // 1. Debe reiniciar la paginación a 0
    expect(component.currentOffset()).toBe(0);
    
    // 2. Debe llamar al API con el término de búsqueda correcto
    expect(mockRequirementService.getDashboardRequirements).toHaveBeenCalledWith('active', 10, 0, 'RRTI-2026');

    // Comprobación del distinctUntilChanged: Si vuelve a emitir el mismo valor, no debe volver a buscar
    mockRequirementService.getDashboardRequirements.mockClear();
    component.searchControl.setValue('RRTI-2026'); // Mismo texto
    tick(500);
    
    // No debe llamarse de nuevo porque el texto no cambió
    expect(mockRequirementService.getDashboardRequirements).not.toHaveBeenCalled();
  }));

  // ========================================================================
  // Criterio T05.3: Persistencia de Contexto (LocalStorage)
  // ========================================================================
  it('Escenario: Sincronización y persistencia de pestañas de navegación', () => {
    // Espiamos al objeto localStorage real del navegador virtual
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');
    
    // Inicializamos en la pestaña por defecto (PROCESO)
    fixture.detectChanges();
    
    // Limpiamos el contador del servicio tras el ngOnInit
    mockRequirementService.getDashboardRequirements.mockClear();

    // Cuando el usuario cambia a la pestaña HISTORICO
    component.switchContext('HISTORICO');

    // Entonces debe guardarse en el LocalStorage
    expect(setItemSpy).toHaveBeenCalledWith('dashboard_context', 'HISTORICO');
    
    // Y el signal debe actualizarse
    expect(component.context()).toBe('HISTORICO');
    
    // Y debe pedir los requerimientos 'finalized'
    expect(mockRequirementService.getDashboardRequirements).toHaveBeenCalledWith('finalized', 10, 0, undefined);
  });
});