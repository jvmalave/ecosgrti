// libs/workflow/src/lib/data-access/services/workflow-phase.service.ts

import { inject, Injectable, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { map } from 'rxjs/operators';
import { 
  RolesInitResponse, 
  RoleStatusUpdateResponse, 
  PhaseCloseResponse, 
  WorkflowRegistersResponse, 
  RawRegistersResponse, 
  WorkflowRecord, 
  WorkflowRegisterPayload,
} from '../models/workflow-phase.models';
import { PhaseConfig } from '../models/phase-config.interface';


@Injectable({
  providedIn: 'root'
})
export class WorkflowPhaseService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private readonly workflowApiUrl = `${this.apiUrl}/workflow`;

  public refreshDashboard$ = new Subject<void>();

  // =========================================================================
  // GESTIÓN DE COMPONENTES PADRE POLIMÓRFICOS
  // =========================================================================

  initializePhaseComponents(reqId: string, config: PhaseConfig): Observable<RolesInitResponse> {
    return this.http.get<RolesInitResponse>(`${this.workflowApiUrl}/requirements/${reqId}/${config.apiEndpoint}/${config.initEndpoint}`);
  }

  // changeComponentStatus(parentId: string, config: PhaseConfig, newStatus: string): Observable<RoleStatusUpdateResponse> {
  //   return this.http.patch<RoleStatusUpdateResponse>(`${this.workflowApiUrl}/${config.apiEndpoint}/${config.parentEntityPath}/${parentId}/status`, { new_status: newStatus });
  // }

  changeComponentStatus(parentId: string, config: PhaseConfig, newStatus: string): Observable<RoleStatusUpdateResponse> {
    
    // 🟢 Truco de Retrocompatibilidad Polimórfica: 
    // Mapeamos 'CLOSED' a 'CLOSE' y 'IN_PROGRESS' a 'REOPEN' para satisfacer a DT
    const actionVal = newStatus === 'CLOSED' ? 'CLOSE' : 'REOPEN';

    const payload = {
      new_status: newStatus, // Para COR y COE
      action: actionVal      // Para el controlador legacy de DT
    };

    return this.http.patch<RoleStatusUpdateResponse>(
      `${this.workflowApiUrl}/${config.apiEndpoint}/${config.parentEntityPath}/${parentId}/status`, 
      payload
    );
  }

  closePhase(reqId: string, config: PhaseConfig): Observable<PhaseCloseResponse> {
    return this.http.patch<PhaseCloseResponse>(`${this.workflowApiUrl}/requirements/${reqId}/${config.apiEndpoint}/close-phase`, {});
  }

  // =========================================================================
  // GESTIÓN DE BITÁCORAS CON PATRÓN ADAPTADOR (Tipado Estricto)
  // =========================================================================

  getChildRegisters(parentId: string, config: PhaseConfig): Observable<WorkflowRegistersResponse> {
    // 🟢 Tipamos explícitamente el GET y el parámetro del map
    return this.http.get<RawRegistersResponse>(`${this.workflowApiUrl}/${config.apiEndpoint}/${config.parentEntityPath}/${parentId}/${config.childEntityPath}`)
      .pipe(
        map((response: RawRegistersResponse) => {
          return {
            reqId: response.id_req,
            parentName: response.nombre_rol ?? response.nombre_entregable ?? 'Sin Nombre',
            parentStatus: response.estado_rol ?? response.estado_entregable ?? 'IN_PROGRESS',
            records: response.registros ?? response.actividades ?? []
          } as WorkflowRegistersResponse;
        })
      );
  }

  storeChildRegister(reqId: string, parentId: string, config: PhaseConfig, payload: WorkflowRegisterPayload): Observable<WorkflowRecord> {
    return this.http.post<WorkflowRecord>(`${this.workflowApiUrl}/requirements/${reqId}/${config.apiEndpoint}/${config.parentEntityPath}/${parentId}/${config.childEntityPath}`, payload);
  }

  updateChildRegister(childId: string, parentId: string, config: PhaseConfig, payload: WorkflowRegisterPayload): Observable<WorkflowRecord> {
    return this.http.put<WorkflowRecord>(`${this.workflowApiUrl}/${config.apiEndpoint}/${config.childEntityPath}/${childId}/${config.parentEntityPath}/${parentId}`, payload);
  }

  deleteChildRegister(childId: string, parentId: string, config: PhaseConfig): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.workflowApiUrl}/${config.apiEndpoint}/${config.childEntityPath}/${childId}/${config.parentEntityPath}/${parentId}`);
  }
}