import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { HttpParams } from '@angular/common/http';

// ==========================================
// IMPORTACIÓN DE MODELOS DE DOMINIO
// ==========================================
import { ApiResponse } from '../models/api-response.model';
import { 
  RequirementDashboard, 
  OrganizationalGraph, 
  CatalogItem, 
  FunctionalConsultantItem, 
  CspeConsultantItem 
} from '../models/requirement.model'

@Injectable({
  providedIn: 'root'
})
export class RequirementService {
  
  private http = inject(HttpClient);
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  private coreApiUrl = `${this.apiUrl}/core/requirements`;
  private securityConsultantsApiUrl = `${this.apiUrl}/consultores`;
  private catalogsApiUrl = `${this.apiUrl}/catalogs`;

  // ==========================================
  //  MÉTODOS
  // ==========================================
  getRequirementTypes(): Observable<ApiResponse<CatalogItem[]>> {
    return this.http.get<ApiResponse<CatalogItem[]>>(`${this.catalogsApiUrl}/requirement-types`);
  }

  getManagementTypes(): Observable<ApiResponse<CatalogItem[]>> {
    return this.http.get<ApiResponse<CatalogItem[]>>(`${this.catalogsApiUrl}/management-types`);
  }

  getFunctionalConsultants(): Observable<ApiResponse<FunctionalConsultantItem[]>> {
    return this.http.get<ApiResponse<FunctionalConsultantItem[]>>(`${this.securityConsultantsApiUrl}/functional-consultants`);
  }

  getCspeConsultants(): Observable<ApiResponse<CspeConsultantItem[]>> {
    return this.http.get<ApiResponse<CspeConsultantItem[]>>(`${this.securityConsultantsApiUrl}/cspe-consultants`);
  }
  
  getOrganizationalLookup(personaId: string): Observable<ApiResponse<OrganizationalGraph>> {
    return this.http.get<ApiResponse<OrganizationalGraph>>(`${this.securityConsultantsApiUrl}/lookup-organizacional/${personaId}`);
  }

  createRequirement(formData: FormData): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(this.coreApiUrl, formData);
  }

  /**
   * US05: Obtener requerimientos para el Dashboard con paginación (Carga Híbrida)
   */
/**
   * US05: Obtener requerimientos para el Dashboard con paginación (Carga Híbrida)
   */
  getDashboardRequirements(
    status: 'active' | 'finalized' = 'active', 
    limit = 10, 
    offset = 0,
    search?: string // <-- NUEVO: Parámetro opcional para el término de búsqueda
  ): Observable<ApiResponse<RequirementDashboard[]>> {
    
    // 1. Usamos 'let' porque HttpParams es inmutable y necesitamos reasignarlo
    let params = new HttpParams()
      .set('status', status)
      .set('limit', limit.toString())
      .set('offset', offset.toString());

    // 2. Si existe un término de búsqueda válido, lo adjuntamos a los parámetros
    if (search) {
      params = params.set('search', search);
    }

    // 3. Mantenemos tu tipado estricto
    return this.http.get<ApiResponse<RequirementDashboard[]>>(this.coreApiUrl, { params });
  }

  
}