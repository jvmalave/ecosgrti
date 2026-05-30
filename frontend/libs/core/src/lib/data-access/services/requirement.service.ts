import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

// ==========================================
// INTERFACES DE DOMINIO 
// ==========================================
export interface RequirementDashboard {
  id: string;
  rrti: string;
  requirement_type: string;
  creation_date: string;
  description: string;
  management_type: string;
  status: string;
  is_locked: boolean;
  cspe_consultants: unknown[]; 
}

export interface OrganizationalGraph {
  persona_id: string;
  functional_consultant_id: string;
  requesting_unit_name: string;
  system_name: string;
  society_name: string;
}

export interface ApiResponse<T> {
  message: string;
  data: T;
}

export interface CatalogItem {
  id: string;
  name: string;
}

export interface FunctionalConsultantItem {
  persona_id: string;
  full_name: string;
}

export interface CspeConsultantItem {
  cspe_id: string;
  full_name: string;
}

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

  getDashboardRequirements(): Observable<ApiResponse<RequirementDashboard[]>> {
    return this.http.get<ApiResponse<RequirementDashboard[]>>(this.coreApiUrl);
  }
}