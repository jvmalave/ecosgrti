import { Injectable, computed, signal } from '@angular/core';
import { WorkflowRequirementState } from '../models/workflow-state.interface';

@Injectable({
  providedIn: 'root' // Mantiene la instancia como un Singleton global dentro del dominio
})
export class WorkflowStateService {
  
  // 1. Fuente de Verdad Privada (SSOT Frontend)
  private readonly _currentRequirement = signal<WorkflowRequirementState | null>(null);

  // 2. Exposición Pública (Solo Lectura) para los componentes
  public readonly currentRequirement = this._currentRequirement.asReadonly();

  /**
   * 3. SIGNAL COMPUTADA DE INMUTABILIDAD (Hard Gate)
   * Reacciona instantáneamente si el backend envía is_locked = true o estado CLOSED.
   * Se usará en los HTML para hacer [disabled]="isLocked()".
   */
  public readonly isLocked = computed<boolean>(() => {
    const req = this._currentRequirement();
    if (!req) return false;
    
    // Basado en tus migraciones, validamos los candados físicos y lógicos
    return req.is_locked === true || req.status === 'CLOSED' || req.status === 'ATF_CLOSED';
  });

  /**
   * 4. SIGNAL COMPUTADA DE PROGRESO
   * Alimenta directamente al ProgressDashboardComponent.
   */
  public readonly currentProgress = computed<number>(() => {
    return this._currentRequirement()?.progress_percentage ?? 0;
  });

  // ==========================================
  // MÉTODOS DE MUTACIÓN
  // ==========================================

  /**
   * Actualiza el estado reactivo tras una consulta HTTP exitosa.
   */
  public updateState(state: WorkflowRequirementState): void {
    this._currentRequirement.set({ ...state });
  }

  /**
   * Limpia el estado (ideal para cuando el usuario sale del detalle del requerimiento).
   */
  public clearState(): void {
    this._currentRequirement.set(null);
  }
}