import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { OrgTreeResponse, Society, SystemNode, RequestingUnit } from '../models/org-structure.model';

@Injectable({
  providedIn: 'root'
})
export class OrgStructureService {
  private readonly http = inject(HttpClient);
  
  // Inyección del token global de la API tal como lo tienes estandarizado
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar datos (ej. tras una mutación)
  private refreshSubject = new Subject<void>();
  
  // Construcción de la URL específica para el dominio de catálogos organizacionales
  private readonly catalogsApiUrl = `${this.apiUrl}/catalogs/org-structure`;

  /**
   * Observable para suscribirse a las peticiones de recarga del árbol
   */
  get refresh$() {
    return this.refreshSubject.asObservable();
  }

  /**
   * Notifica a los suscriptores que deben recargar la data
   */
  notifyRefresh() {
    this.refreshSubject.next();
  }

  /**
   * Obtiene el árbol jerárquico completo (Sociedades, Sistemas, Unidades).
   */
  getOrgTree(): Observable<OrgTreeResponse> {
    return this.http.get<OrgTreeResponse>(`${this.catalogsApiUrl}/tree`);
  }

  /**
   * Registra una nueva Sociedad (Nivel 1).
   */
  createSociety(payload: Pick<Society, 'name' | 'acronym'>): Observable<{ message: string; society_id: string }> {
    return this.http.post<{ message: string; society_id: string }>(`${this.catalogsApiUrl}/societies`, payload);
  }

  /**
   * Registra un nuevo Sistema (Nivel 2).
   */
  createSystem(payload: Pick<SystemNode, 'name' | 'society_id'>): Observable<{ message: string; system_id: string }> {
    return this.http.post<{ message: string; system_id: string }>(`${this.catalogsApiUrl}/systems`, payload);
  }

  /**
   * Registra una nueva Unidad Solicitante (Nivel 3).
   */
  createRequestingUnit(payload: Pick<RequestingUnit, 'name' | 'system_id'>): Observable<{ message: string; unit_id: string }> {
    return this.http.post<{ message: string; unit_id: string }>(`${this.catalogsApiUrl}/requesting-units`, payload);
  }

  /**
   * Actualiza los datos de una Sociedad (Nivel 1).
   */
  updateSociety(id: string, payload: Pick<Society, 'name' | 'acronym'>): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.catalogsApiUrl}/societies/${id}`, payload);
  }

  /**
   * Actualiza los datos de un Sistema (Nivel 2).
   */
  updateSystem(id: string, payload: Pick<SystemNode, 'name' | 'society_id'>): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.catalogsApiUrl}/systems/${id}`, payload);
  }

  /**
   * Actualiza los datos de una Unidad Solicitante (Nivel 3).
   */
  updateRequestingUnit(id: string, payload: Pick<RequestingUnit, 'name' | 'system_id'>): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.catalogsApiUrl}/requesting-units/${id}`, payload);
  }

  /**
   * Actualiza el estatus (is_active) para aplicar borrado lógico o reactivación.
   */
  updateNodeStatus(
    nodeType: 'societies' | 'systems' | 'requesting-units', 
    id: string, 
    isActive: boolean
  ): Observable<{ message: string }> {
    return this.http.patch<{ message: string }>(
      `${this.catalogsApiUrl}/${nodeType}/${id}/status`, 
      { is_active: isActive }
    );
  }
}