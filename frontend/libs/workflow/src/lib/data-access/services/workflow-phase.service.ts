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
  PiTestUserListResponse,
  PiTestUserActionResponse,
  PiTestUserDeleteResponse,
  PiTestUserPayload,
  PiFunctionalApprovalResponse
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


  changeComponentStatus(parentId: string, config: PhaseConfig, newStatus: string): Observable<RoleStatusUpdateResponse> {
    // Mapeamos 'CLOSED' a 'CLOSE' y 'IN_PROGRESS' a 'REOPEN' para satisfacer a DT
    const actionVal = newStatus === 'CLOSED' ? 'CLOSE' : 'REOPEN';

    const payload = {
      status: newStatus,     // PARA PI (Satisface al PiRoleController)
      new_status: newStatus, // PARA COR y COE (Retrocompatibilidad)
      action: actionVal      // PARA DT (Retrocompatibilidad Legacy)
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

  // =========================================================================
  // GESTIÓN DE USUARIOS DE PRUEBA (CU-042 - FASE PI)
  // =========================================================================

  getPiTestUsers(roleId: string): Observable<PiTestUserListResponse> {
    return this.http.get<PiTestUserListResponse>(`${this.workflowApiUrl}/pi/roles/${roleId}/test-users`);
  }

  addPiTestUser(roleId: string, requirementId: string, identifier: string, force = false): Observable<PiTestUserActionResponse> {
    const payload: PiTestUserPayload = {
      requirement_id: requirementId,
      identifier: identifier,
      force: force
    };
    return this.http.post<PiTestUserActionResponse>(`${this.workflowApiUrl}/pi/roles/${roleId}/test-users`, payload);
  }

  deletePiTestUser(userId: string): Observable<PiTestUserDeleteResponse> {
    return this.http.delete<PiTestUserDeleteResponse>(`${this.workflowApiUrl}/pi/test-users/${userId}`);
  }

  // =========================================================================
  // GESTIÓN DE APROBACIÓN FUNCIONAL (CU-044 - FASE PI)
  // =========================================================================

  uploadFunctionalApproval(reqId: string, roleId: string, file: File): Observable<PiFunctionalApprovalResponse> {
    const formData = new FormData();
    formData.append('file', file);

    return this.http.post<PiFunctionalApprovalResponse>(
      `${this.workflowApiUrl}/requirements/${reqId}/pi/roles/${roleId}/approvals`, 
      formData
    );
  }

  downloadFunctionalApproval(reqId: string, roleId: string): Observable<Blob> {
    return this.http.get(
      `${this.workflowApiUrl}/requirements/${reqId}/pi/roles/${roleId}/approvals/download`, 
      { responseType: 'blob' } // Para manejar archivo binario
    );
  }
  
}