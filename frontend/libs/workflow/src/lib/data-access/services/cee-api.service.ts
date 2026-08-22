import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { CeeDeliverable, CeeTicket } from '../models/cee-workflow.model';

@Injectable({ providedIn: 'root' })
export class CeeApiService {
  private readonly http = inject(HttpClient);
  
  // Configuración de Inyección Global
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private readonly workflowApiUrl = `${this.apiUrl}/workflow`;
  private readonly ceeApiUrl = `${this.workflowApiUrl}/cee`;

  // Subject para comunicación entre componentes
  public refreshDashboard$ = new Subject<void>();

  /**
   * CU-077: Inicializar y Listar Entregables en CE-E
   */
  public initDeliverables(requirementId: string): Observable<{ requirement_id: string; deliverables: CeeDeliverable[] }> {
    return this.http.get<{ requirement_id: string; deliverables: CeeDeliverable[] }>(
      `${this.ceeApiUrl}/requirements/${requirementId}/deliverables-init`
      
      //api/workflow/cee/requirements/${requirementId}/deliverables-init
    );
  }

  /**
   * CU-079: Registrar Ticket de Certificación (FormData)
   */
  public storeTicket(requirementId: string, formData: FormData): Observable<{ message: string; ticket: CeeTicket }> {
    return this.http.post<{ message: string; ticket: CeeTicket }>(
      `${this.ceeApiUrl}/requirements/${requirementId}/tickets`,
      formData
    );
  }

  /**
   * CU-079: Actualizar Ticket de Certificación
   */
  public updateTicket(ticketId: string, formData: FormData): Observable<{ message: string; ticket: CeeTicket }> {
    return this.http.post<{ message: string; ticket: CeeTicket }>(
      `${this.ceeApiUrl}/tickets/${ticketId}`,
      formData
    );
  }

  /**
   * CU-080: Registrar Dictamen Funcional y Bifurcación
   */
  public registerResult(ticketId: string, formData: FormData): Observable<{ message: string; ticket: CeeTicket }> {
    return this.http.post<{ message: string; ticket: CeeTicket }>(
      `${this.ceeApiUrl}/tickets/${ticketId}/results`,
      formData
    );
  }

  /**
   * CU-080: Actualizar Dictamen (Step-Up Authentication)
   */
  public updateResultWithChallenge(ticketId: string, formData: FormData): Observable<{ message: string; ticket: CeeTicket }> {
    return this.http.post<{ message: string; ticket: CeeTicket }>(
      `${this.ceeApiUrl}/tickets/${ticketId}/results-update`,
      formData
    );
  }

  public downloadTicketFile(ticketId: string): Observable<Blob> {
    const url = `${this.ceeApiUrl}/tickets/${ticketId}/file`; 
    return this.http.get(url, { responseType: 'blob' });
  }

  /**
   * CU-081: Cierre Global de la Fase
   */
  public closePhase(requirementId: string): Observable<{ success: boolean; message: string; progress_percentage: number }> {
    return this.http.patch<{ success: boolean; message: string; progress_percentage: number }>(
      `${this.ceeApiUrl}/requirements/${requirementId}/close`,
      {}
    );
  }

  public downloadRequestFile(ticketId: string): Observable<Blob> {
    const url = `${this.ceeApiUrl}/tickets/${ticketId}/request-file`; 
    return this.http.get(url, { responseType: 'blob' });
  }

  public downloadResultFile(ticketId: string): Observable<Blob> {
    const url = `${this.ceeApiUrl}/tickets/${ticketId}/result-file`; 
    return this.http.get(url, { responseType: 'blob' });
  }
}