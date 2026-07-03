import { describe, it, expect, beforeEach, vi } from 'vitest';
import { ComponentFixture, TestBed, fakeAsync, tick } from '@angular/core/testing';
import { of } from 'rxjs';
import { signal } from '@angular/core';
import { ReactiveFormsModule } from '@angular/forms';

// 1. VIAJAMOS HACIA LA LIBRERÍA CORE (Ajusta la ruta si es necesario)
// 1. Apagamos la regla de Nx estrictamente para esta línea
// eslint-disable-next-line @nx/enforce-module-boundaries
import { DashboardComponent } from '@sgrti/core'; 

// eslint-disable-next-line @nx/enforce-module-boundaries
import { RequirementService } from '@sgrti/core'; // O la ruta que estés usando

import { AuthService } from '@ecosgrti/security/data-access';

// 2. EL ALIAS FUNCIONA PERFECTO AQUÍ ADENTRO

describe('DashboardComponent (US05 - BDD Scenarios)', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;
  // 💡 Expandimos el tipo seguro incorporando el Observable correspondiente
  let mockRequirementService: { 
    getDashboardRequirements: ReturnType<typeof vi.fn>;
    refreshDashboard$: import('rxjs').Observable<unknown>; 
  };
  let mockAuthService: { currentUser: unknown, logout: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    mockRequirementService = {
      // 💡 Asignamos el flujo tipado de forma segura
      refreshDashboard$: of(null), 
      
      getDashboardRequirements: vi.fn().mockReturnValue(of({
        message: 'Éxito',
        data: [{ rrti: 'RRTI-2026-001', requirement_type: 'Test' }],
        meta: { has_more: false }
      }))
    };//

    mockAuthService = {
      currentUser: signal({ id: '123', name: 'Coordinador CSPE' }),
      logout: vi.fn().mockReturnValue(of(true))
    };

    localStorage.clear();
    
    await TestBed.configureTestingModule({
  // 💡 Importamos ReactiveFormsModule para dar soporte a searchControl.valueChanges
    imports: [DashboardComponent, ReactiveFormsModule], 
    providers: [
    { provide: RequirementService, useValue: mockRequirementService },
    { provide: AuthService, useValue: mockAuthService },
    { provide: 'Router', useValue: { navigate: vi.fn() } }
  ]
  }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
  });

  it('Escenario: Carga inicial de requerimientos (Dashboard)', () => {
    fixture.detectChanges();
    expect(mockRequirementService.getDashboardRequirements).toHaveBeenCalledWith('active', 10, 0, undefined);
    expect(component.requirements().length).toBe(1);
    expect(component.requirements()[0].rrti).toBe('RRTI-2026-001');
  });

  it('Escenario: Búsqueda reactiva optimizada por código RRTI', () => {
    // 1. Secuestramos el reloj del sistema usando Vitest en lugar de Angular fakeAsync
    vi.useFakeTimers();
    
    fixture.detectChanges();
    mockRequirementService.getDashboardRequirements.mockClear();

    // 2. El usuario escribe
    component.searchControl.setValue('RRTI-2026');
    expect(mockRequirementService.getDashboardRequirements).not.toHaveBeenCalled();

    // 3. Avanzamos el tiempo del universo en exactamente 500ms
    vi.advanceTimersByTime(500);

    // 4. Verificamos los resultados
    expect(component.currentOffset()).toBe(0);
    expect(mockRequirementService.getDashboardRequirements).toHaveBeenCalledWith('active', 10, 0, 'RRTI-2026');

    // 5. Verificamos el DistinctUntilChanged (mismo texto)
    mockRequirementService.getDashboardRequirements.mockClear();
    component.searchControl.setValue('RRTI-2026');
    
    vi.advanceTimersByTime(500);
    expect(mockRequirementService.getDashboardRequirements).not.toHaveBeenCalled();

    // 6. Devolvemos el reloj a la normalidad
    vi.useRealTimers();
  });

  it('Escenario: Sincronización y persistencia de pestañas de navegación', () => {
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');
    fixture.detectChanges();
    mockRequirementService.getDashboardRequirements.mockClear();

    component.switchContext('HISTORICO');

    expect(setItemSpy).toHaveBeenCalledWith('dashboard_context', 'HISTORICO');
    expect(component.context()).toBe('HISTORICO');
    expect(mockRequirementService.getDashboardRequirements).toHaveBeenCalledWith('finalized', 10, 0, undefined);
  });
});