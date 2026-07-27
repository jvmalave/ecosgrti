import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { 
  UnifiedPersonPayload, 
  UnifiedPersonResponse, 
  RequestingUnitOption, 
  ApiResponse,
  UnifiedPerson,
  PaginatedResponse 
} from '../models/unified-person.model';

@Injectable({
  providedIn: 'root'
})
export class UnifiedPersonService {
  private readonly http = inject(HttpClient);
  
  // Inyección del token global de la API utilizando la convención del monorepo
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Construcción de la URL específica para el dominio Security / MDM
  private mdmApiUrl = `${this.apiUrl}/mdm/persons`; 
  private mdmRequestingUnitsUrl = `${this.apiUrl}/mdm/requesting-units`;

  /**
   * Obtiene el listado paginado de identidades con sus relaciones (Eager Loading).
   * @param page Número de página para la paginación de Laravel
   */
  public getIdentities(page = 1): Observable<PaginatedResponse<UnifiedPerson>> {
    const params = new HttpParams().set('page', page.toString());
    return this.http.get<PaginatedResponse<UnifiedPerson>>(this.mdmApiUrl, { params });
  }

  /**
   * Obtiene el detalle de una identidad específica por su UUID.
   * @param id Identificador UUID de la persona
   */
  public getIdentityById(id: string): Observable<ApiResponse<UnifiedPerson>> {
    return this.http.get<ApiResponse<UnifiedPerson>>(`${this.mdmApiUrl}/${id}`);
  }

  /**
   * Envía el payload al backend para aprovisionar una nueva identidad unificada.
   * @param payload Datos estructurados y validados desde el formulario reactivo
   */
  public createUnifiedPerson(payload: UnifiedPersonPayload): Observable<UnifiedPersonResponse> {
    return this.http.post<UnifiedPersonResponse>(this.mdmApiUrl, payload);
  }

  /**
   * Actualiza una Ficha Unificada existente sincronizando sus perfiles en cascada.
   * @param id Identificador UUID de la persona
   * @param payload Datos actualizados del formulario
   */
  public updateUnifiedPerson(id: string, payload: UnifiedPersonPayload): Observable<ApiResponse<UnifiedPerson>> {
    return this.http.put<ApiResponse<UnifiedPerson>>(`${this.mdmApiUrl}/${id}`, payload);
  }

  /**
   * Ejecuta la inhabilitación temporal (Soft Delete) de la ficha unificada.
   * @param id Identificador UUID de la persona
   */
  public deleteUnifiedPerson(id: string): Observable<ApiResponse<null>> {
    return this.http.delete<ApiResponse<null>>(`${this.mdmApiUrl}/${id}`);
  }

  /**
   * Consulta el catálogo de Unidades Solicitantes.
   * Transforma la respuesta envelope a un arreglo fuertemente tipado.
   */
  public getRequestingUnits(): Observable<RequestingUnitOption[]> {
    return this.http.get<ApiResponse<RequestingUnitOption[]>>(this.mdmRequestingUnitsUrl)
      .pipe(
        // Extraemos estrictamente el arreglo 'data'
        map((response: ApiResponse<RequestingUnitOption[]>) => response.data || [])
      );
  }
}