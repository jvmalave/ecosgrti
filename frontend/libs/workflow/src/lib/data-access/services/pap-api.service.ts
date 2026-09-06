import { Injectable, ProviderToken, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PapRolesInitResponse, PapOrderResponse, PapResultResponse, ClosePhaseResponse } from '../models/pap-workflow.model';




@Injectable({
  providedIn: 'root'
})
export class PapApiService {
  private readonly http = inject(HttpClient);
   // Configuración de Inyección Global
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private readonly workflowApiUrl = `${this.apiUrl}/workflow`;
  private readonly papApiUrl = `${this.workflowApiUrl}/pap`;


  /**
   * Inicializa la fase PAP (CU-054)
   */
  public initRoles(requirementId: string): Observable<PapRolesInitResponse> {
    return this.http.get<PapRolesInitResponse>(`${this.papApiUrl}/requirements/${requirementId}/roles-init`);
  }

  /**
   * Registra una nueva Orden de Transporte y promueve los roles (CU-056)
   */
  public storeOrder(requirementId: string, formData: FormData): Observable<PapOrderResponse> {
    // Usamos el papApiUrl que corregiste en el paso anterior
    return this.http.post<PapOrderResponse>(`${this.papApiUrl}/requirements/${requirementId}/orders`, formData);
  }

  /**
   * Registra el dictamen de una Orden de Transporte (CU-057)
   */
  public registerResult(orderId: string, formData: FormData): Observable<PapResultResponse> {
    return this.http.post<PapResultResponse>(`${this.papApiUrl}/orders/${orderId}/results`, formData);
  }

  public downloadOrderFile(orderId: string): Observable<Blob> {
    return this.http.get(`${this.papApiUrl}/orders/${orderId}/download-file`, {
      responseType: 'blob'
    });
  }

  /**
   * Descarga el documento (Acta) del Dictamen de Despliegue
   */
  public downloadResultFile(orderId: string): Observable<Blob> {
    return this.http.get(`${this.papApiUrl}/orders/${orderId}/download-result`, {
      responseType: 'blob'
    });
  }

  public closePhase(requirementId: string): Observable<ClosePhaseResponse> {
    return this.http.patch(`${this.papApiUrl}/requirements/${requirementId}/close-phase`, {});
  }

}