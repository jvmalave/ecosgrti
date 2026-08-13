import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { AtfAgreementPayload, AtfAgreementResponse, AtfAgreementDetail, UpdateManagementTypeResponse, ClosureReadinessResponse, ClosureResponse } from '../models/atf-agreement.model';

@Injectable({
  providedIn: 'root'
})
export class WorkflowApiService {
  private http = inject(HttpClient);
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar datos
  public refreshDashboard$ = new Subject<void>();
  
  // Construcción de la URL específica para este dominio
  private workflowApiUrl = `${this.apiUrl}/workflow/requirements`;

  /**
   * Registra un nuevo acuerdo ATF para un requerimiento específico.
   * @param requirementId UUID del requerimiento
   * @param payload Datos del acuerdo
   */
  public postAgreement(requirementId: string, payload: AtfAgreementPayload): Observable<AtfAgreementResponse> {
    return this.http.post<AtfAgreementResponse>(
      `${this.workflowApiUrl}/${requirementId}/atf-agreements`, 
      payload
    );
  }
  /**
  * Obtiene la lista de acuerdos de un requerimiento (CU-014).
   */
  public getAgreements(requirementId: string): Observable<{ data: AtfAgreementDetail[] }> {
    return this.http.get<{ data: AtfAgreementDetail[] }>(
      `${this.workflowApiUrl}/${requirementId}/atf-agreements`
    );
  }

  public putAgreement(requirementId: string, agreementId: string, payload: AtfAgreementPayload): Observable<AtfAgreementResponse> {
    return this.http.put<AtfAgreementResponse>(
      `${this.workflowApiUrl}/${requirementId}/atf-agreements/${agreementId}`, 
      payload
    );
  }

  /**
   * Elimina un acuerdo (Soft Delete)
   */
  public deleteAgreement(requirementId: string, agreementId: string): Observable<AtfAgreementResponse> {
    return this.http.delete<AtfAgreementResponse>(
      `${this.workflowApiUrl}/${requirementId}/atf-agreements/${agreementId}`
    );
  }

  /**
   * Actualiza el tipo de gestión de un requerimiento y recalcula su progreso (CU-017.5)
   */
  public updateManagementType(requirementId: string, tipoGestion: string): Observable<UpdateManagementTypeResponse> {
    const payload = { tipo_gestion: tipoGestion };
    
    return this.http.patch<UpdateManagementTypeResponse>(
      `${this.workflowApiUrl}/${requirementId}/management-type`,
      payload
    );
  }

  /**
   * FASE 1: Evalúa si el requerimiento cumple el quórum para cerrar la fase ATF.
   * Endpoint: GET /workflow/requirements/{id}/closure-readiness
   */
  public checkClosureReadiness(requirementId: string): Observable<ClosureReadinessResponse> {
    return this.http.get<ClosureReadinessResponse>(`${this.workflowApiUrl}/${requirementId}/closure-readiness`);
  }

  /**
   * FASE 2: Ejecuta el cierre atómico (Hard Gate) de la fase ATF.
   *
   */
  public closeAtfPhase(requirementId: string): Observable<ClosureResponse> {
    // Enviamos un payload vacío {} ya que el backend toma el ID de la URL y el usuario del token
    return this.http.post<ClosureResponse>(`${this.workflowApiUrl}/${requirementId}/close-atf`, {});
  }



}