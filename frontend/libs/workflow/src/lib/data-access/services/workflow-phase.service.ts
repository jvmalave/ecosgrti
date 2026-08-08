// libs/workflow/src/lib/data-access/services/workflow-phase.service.ts

import { inject, Injectable, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { 
  RolesInitResponse, 
  RoleStatusUpdateResponse, 
  PhaseCloseResponse, 
  RegistersListResponse, 
  WorkflowRegister, 
  WorkflowRegisterPayload 
} from '../models/workflow-phase.models';

@Injectable({
  providedIn: 'root'
})
export class WorkflowPhaseService {
  private readonly http = inject(HttpClient);
  
  // Inyección estricta de la URL global
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // URL base unificada para las rutas del módulo
  private readonly workflowApiUrl = `${this.apiUrl}/workflow`;

  // Subject para notificar a otros componentes que deben recargar datos
  public refreshDashboard$ = new Subject<void>();

  // =========================================================================
  // GESTIÓN DE ROLES POLIMÓRFICOS
  // =========================================================================

  /**
   * Inicializa y recupera los roles de la fase activa.
   */
  initializeRoles(reqId: string, phaseEndpoint: string): Observable<RolesInitResponse> {
    return this.http.get<RolesInitResponse>(`${this.workflowApiUrl}/requirements/${reqId}/${phaseEndpoint}/roles-init`);
  }

  /**
   * Actualiza el estado del rol (Ej. IN_PROGRESS -> CLOSED).
   */
  changeRoleStatus(roleId: string, phaseEndpoint: string, newStatus: string): Observable<RoleStatusUpdateResponse> {
    return this.http.patch<RoleStatusUpdateResponse>(`${this.workflowApiUrl}/${phaseEndpoint}/roles/${roleId}/status`, { new_status: newStatus });
  }

  /**
   * Cierra la fase de forma global (Avanza a la siguiente).
   */
  closePhase(reqId: string, phaseEndpoint: string): Observable<PhaseCloseResponse> {
    return this.http.patch<PhaseCloseResponse>(`${this.workflowApiUrl}/requirements/${reqId}/${phaseEndpoint}/close-phase`, {});
  }

  // =========================================================================
  // GESTIÓN DE BITÁCORAS / REGISTROS POLIMÓRFICOS
  // =========================================================================

  /**
   * Recupera la lista de registros de la bitácora técnica de un rol.
   */
  getRegisters(roleId: string, phaseEndpoint: string): Observable<RegistersListResponse> {
    return this.http.get<RegistersListResponse>(`${this.workflowApiUrl}/${phaseEndpoint}/roles/${roleId}/registers`);
  }

  /**
   * Crea un nuevo registro en la bitácora.
   */
  storeRegister(reqId: string, roleId: string, phaseEndpoint: string, payload: WorkflowRegisterPayload): Observable<WorkflowRegister> {
    return this.http.post<WorkflowRegister>(`${this.workflowApiUrl}/requirements/${reqId}/${phaseEndpoint}/roles/${roleId}/registers`, payload);
  }

  /**
   * Actualiza un registro existente en la bitácora.
   */
  updateRegister(regId: string, roleId: string, phaseEndpoint: string, payload: WorkflowRegisterPayload): Observable<WorkflowRegister> {
    return this.http.put<WorkflowRegister>(`${this.workflowApiUrl}/${phaseEndpoint}/registers/${regId}/roles/${roleId}`, payload);
  }

  /**
   * Elimina lógicamente un registro de la bitácora.
   */
  deleteRegister(regId: string, roleId: string, phaseEndpoint: string): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.workflowApiUrl}/${phaseEndpoint}/registers/${regId}/roles/${roleId}`);
  }
}