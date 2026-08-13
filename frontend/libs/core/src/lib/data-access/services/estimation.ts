import { inject, Injectable, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { EstimationPayload, EstimationDetailsResponse } from '../models/requirement.model';

/**
 * Definicion del contrato estricto para el payload de cierre,
 */
export interface ClosePlanningPayload {
  justification: string;
}

@Injectable({
  providedIn: 'root'
})
export class EstimationService {
  private http = inject(HttpClient);
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private coreApiUrl = `${this.apiUrl}/core/requirements`;

  /**
   * Guarda la data (Draft). NO bloquea el requerimiento.
   */
  saveEstimation(requirementId: string, payload: EstimationPayload): Observable<{success: boolean, message: string}> {
    return this.http.put<{success: boolean, message: string}>(
      `${this.coreApiUrl}/${requirementId}/estimation`, 
      payload
    );
  }

  /**
   * Aplicar el Hard Gate y cerrar la fase.
    * Bloquea el requerimiento para futuras ediciones.
   */
  closePlanningPhase(requirementId: string, justification: string): Observable<{success: boolean, message: string}> {
    const payload: ClosePlanningPayload = { justification };
    
    return this.http.patch<{success: boolean, message: string}>(
      `${this.coreApiUrl}/${requirementId}/close-planning`, 
      payload
    );
  }

  /**
   * Obtiene los datos del requerimiento, RRTI, estado y el borrador de estimación si existe.
   */
  getEstimationDetails(requirementId: string): Observable<EstimationDetailsResponse> {
    return this.http.get<EstimationDetailsResponse>(`${this.coreApiUrl}/${requirementId}/estimation`);
  }
}