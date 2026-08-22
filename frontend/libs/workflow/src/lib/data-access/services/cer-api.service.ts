import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { CerRole, CerTicket } from '../models/cer-workflow.model';

@Injectable({ providedIn: 'root' })
export class CerApiService {
  private readonly http = inject(HttpClient);
  
  // Configuración de Inyección Global
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private readonly workflowApiUrl = `${this.apiUrl}/workflow`;
  private readonly cerApiUrl = `${this.workflowApiUrl}/cer`;

  // Subject para comunicación entre componentes (Ej: actualizar contadores en el Dashboard)
  public refreshDashboard$ = new Subject<void>();

  /**
   * CU-047: Inicializar y Listar Roles en CER
   */
  public initRoles(requirementId: string): Observable<{ requirement_id: string; roles: CerRole[] }> {
    return this.http.get<{ requirement_id: string; roles: CerRole[] }>(
      `${this.cerApiUrl}/requirements/${requirementId}/roles-init`
    );
  }

  /**
   * CU-049: Registrar Ticket de Certificación
   * Nota: Usamos FormData porque Angular enviará un archivo PDF
   */
  public storeTicket(requirementId: string, formData: FormData): Observable<{ message: string; ticket: CerTicket }> {
    return this.http.post<{ message: string; ticket: CerTicket }>(
      `${this.cerApiUrl}/requirements/${requirementId}/tickets`,
      formData
    );
  }

  /**
   * CU-049: Actualizar Ticket de Certificación
   */
  public updateTicket(ticketId: string, formData: FormData): Observable<{ message: string; ticket: CerTicket }> {
    return this.http.post<{ message: string; ticket: CerTicket }>(
      `${this.cerApiUrl}/tickets/${ticketId}`,
      formData
    );
  }

  /**
   * CU-050: Registrar Dictamen Funcional y Bifurcación
   */
  public registerResult(ticketId: string, formData: FormData): Observable<{ message: string; ticket: CerTicket }> {
    return this.http.post<{ message: string; ticket: CerTicket }>(
      `${this.cerApiUrl}/tickets/${ticketId}/results`,
      formData
    );
  }

  /**
   * CU-050: Actualizar Dictamen (Step-Up Authentication)
   */
  public updateResultWithChallenge(ticketId: string, formData: FormData): Observable<{ message: string; ticket: CerTicket }> {
    return this.http.post<{ message: string; ticket: CerTicket }>(
      `${this.cerApiUrl}/tickets/${ticketId}/results-update`,
      formData
    );
  }

  public downloadTicketFile(ticketId: string): Observable<Blob> {
    const url = `${this.cerApiUrl}/tickets/${ticketId}/file`; 
    return this.http.get(url, { responseType: 'blob' });
  }


  public downloadRequestFile(ticketId: string): Observable<Blob> {
    const url = `${this.cerApiUrl}/tickets/${ticketId}/request-file`; 
    return this.http.get(url, { responseType: 'blob' });
  }

  /**
   * CU-051: Cierre Global de la Fase
   */
  public closePhase(requirementId: string): Observable<{ success: boolean; message: string; progress_percentage: number }> {
    return this.http.patch<{ success: boolean; message: string; progress_percentage: number }>(
      `${this.cerApiUrl}/requirements/${requirementId}/close`,
      {}
    );
  }
}