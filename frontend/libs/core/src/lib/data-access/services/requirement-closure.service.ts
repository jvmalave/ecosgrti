import { Injectable, ProviderToken, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';

import { ClosureDraftResponse, ClosureFinalResponse } from '../models/closure.model';



@Injectable({
  providedIn: 'root'
})
export class RequirementClosureService {
  
  private http = inject(HttpClient);
  
  // Asumiendo que las rutas bajo workflow inician de esta manera
  //private readonly baseUrl = `${environment.apiUrl}/workflow/requirements`;

  private globalApiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar datos
  public refreshDashboard$ = new Subject<void>();
  
  // Construcción de URLs específicas según el dominio y la inmutabilidad del vínculo
  private clousureReqApiUrl = `${this.globalApiUrl}/core/requirements`;
 

  /**
   * (Etapa 1): Consume el endpoint para compilar el acta borrador al vuelo.
   * @param requirementId ID del requerimiento a evaluar.
   * @param payload Contiene fechas, checkbox y el archivo PDF (FormData).
   */
  public generateDraft(requirementId: string, payload: FormData): Observable<ClosureDraftResponse> {
    const url = `${this.clousureReqApiUrl}/${requirementId}/generate-closure-act`;
    return this.http.post<ClosureDraftResponse>(url, payload);
  }

  /**
   * Transición Atómica de Cierre Definitivo.
   * @param requirementId ID del requerimiento a cerrar.
   * @param payload Contiene los mismos datos confirmados para el sellado inmutable.
   */
  public finalizeClosure(requirementId: string, payload: FormData): Observable<ClosureFinalResponse> {
    const url = `${this.clousureReqApiUrl}/${requirementId}/finalize-closure`;
    return this.http.post<ClosureFinalResponse>(url, payload);
  }
}