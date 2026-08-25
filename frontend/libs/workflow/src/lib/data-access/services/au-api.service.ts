import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { AuRolesInitResponse, AuTicketResponse } from '../models/au-workflow.model';

export interface FinalizePhaseResponse {
  status: string;
  message: string;
  data: {
    req_id: string;
    progreso_global: number;
  };
}

@Injectable({
  providedIn: 'root'
})
export class AuApiService {
  private http = inject(HttpClient);
  
  // Configuración de Inyección Global
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private readonly workflowApiUrl = `${this.apiUrl}/workflow`;
  private readonly auApiUrl = `${this.workflowApiUrl}/au`;

  // Subject para comunicación entre componentes (Reactividad del Dashboard)
  public refreshDashboard$ = new Subject<void>();

  /**
   * Inicializar y Migrar Roles desde PAP hacia AU
   */
  public initRoles(requirementId: string): Observable<AuRolesInitResponse> {
    return this.http.get<AuRolesInitResponse>(`${this.auApiUrl}/requirements/${requirementId}/roles-init`);
  }

  /**
   * Registrar nueva solicitud de Ticket (CSAL) y subir planillas Multipart
   */
  public storeTicket(requirementId: string, formData: FormData): Observable<AuTicketResponse> {
    return this.http.post<AuTicketResponse>(`${this.auApiUrl}/requirements/${requirementId}/tickets`, formData);
  }

  /**
   * Registrar Resultados del Ticket CSAL (Evaluación Granular y PDF)
   */
  public registerResult(ticketId: string, formData: FormData): Observable<AuTicketResponse> {
    return this.http.post<AuTicketResponse>(`${this.auApiUrl}/tickets/${ticketId}/results`, formData);
  }

  /**
   * Descargar Documento PDF
   */
  public downloadDocument(path: string): Observable<Blob> {
    const url = `${this.auApiUrl}/tickets/download-document?path=${encodeURIComponent(path)}`;
    return this.http.get(url, {
      responseType: 'blob' 
    });
  }

  /**
   * CU-065: Finalizar fase AU (Cierre global del requerimiento)
   */
  public finalizeAuPhase(requirementId: string): Observable<FinalizePhaseResponse> {
    return this.http.post<FinalizePhaseResponse>(`${this.auApiUrl}/requirements/${requirementId}/finalize-au`, {});
  }
}