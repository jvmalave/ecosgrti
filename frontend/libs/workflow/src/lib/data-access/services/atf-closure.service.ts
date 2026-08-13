import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { ClosureReadinessResponse, ClosureResponse } from '../models/atf-agreement.model';



@Injectable({
  providedIn: 'root',
})
export class AtfClosureService {

  private http = inject(HttpClient);
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar datos
  public refreshDashboard$ = new Subject<void>();
  
  // Construcción de la URL específica para este dominio
  private workflowApiUrl = `${this.apiUrl}/workflow/requirements`;


  // Subject reactivo para notificar a todos los componentes hijos que la fase se cerró
  public atfClosed$ = new Subject<void>();

  /**
   * Evalúar si el requerimiento cumple el quórum para cerrar la fase ATF.
   * @param requirementId 
   */
  public checkClosureReadiness(requirementId: string): Observable<ClosureReadinessResponse> {
    return this.http.get<ClosureReadinessResponse>(`${this.workflowApiUrl}/${requirementId}/closure-readiness`);
  }

  /**
   * FASE 2: Ejecuta el cierre atómico (Hard Gate) de la fase ATF.
   * @param requirementId
   */
  public closeAtfPhase(requirementId: string): Observable<ClosureResponse> {
    // Se Envia un payload vacío {} y se forza el tipado de retorno a ClosureResponse
    return this.http.post<ClosureResponse>(`${this.workflowApiUrl}/${requirementId}/close-atf`, {});
  }

}
