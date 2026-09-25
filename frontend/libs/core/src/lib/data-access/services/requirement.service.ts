import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
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
  CspeConsultantItem,
  DeletionTicketResponse,
  RequirementDetail
} from '../models/requirement.model'

@Injectable({
  providedIn: 'root'
})
export class RequirementService {
  
  private http = inject(HttpClient);
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  public refreshDashboard$ = new Subject<void>();
  private coreApiUrl = `${this.apiUrl}/core/requirements`;
  private securityConsultantsApiUrl = `${this.apiUrl}/consultores`;
  private catalogsApiUrl = `${this.apiUrl}/catalogs`;

  // ========================================================================
  //  MÉTODOS DE CATALOGOS, GRAFO ORGANIZACIONAL Y CREACION DE REQUERIMIENTOS
  // ========================================================================
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


  // ====================================================================
  // MÉTODOS MOSTRAR Y EDITAR REQUERIMIENTOS (Modal)
  // ====================================================================
  
  /**
   * Obtiener el detalle completo del requerimiento para el Modal.
   */
  getRequirementDetail(id: string): Observable<ApiResponse<RequirementDetail>> {
    return this.http.get<ApiResponse<RequirementDetail>>(`${this.coreApiUrl}/${id}`);
  }

  /**
   * Envíar los cambios al backend, soportando archivos físicos.
   */
  updateRequirement(id: string, formData: FormData): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.coreApiUrl}/${id}`, formData);
  }

public downloadPrivateDocument(path: string): void {
    const url = `${this.apiUrl}/core/requirements/download-doc`;
    
    this.http.get(url, { 
      params: { path: path }, 
      responseType: 'blob' // Obligatorio para manejar archivos binarios con HttpClient
    }).subscribe({
      next: (blob) => {
        const fileURL = URL.createObjectURL(blob);
        window.open(fileURL, '_blank'); // Abre el PDF en una pestaña de forma segura
      },
      error: (err) => {
        console.error('Error al descargar el documento privado:', err);
      }
    });
  }

  // ====================================================================
  // MÉTODOS DEL DASHBOARD Y BORRADO LÓGICO
  // ====================================================================
  
/**
   * Obtener requerimientos para el Dashboard con paginación (Carga Híbrida)
   */
  getDashboardRequirements(
    status: 'active' | 'finalized' = 'active', 
    limit = 10, 
    offset = 0,
    search?: string // <-- NUEVO: Parámetro opcional para el término de búsqueda
  ): Observable<ApiResponse<RequirementDashboard[]>> {
    
    // Se usa 'let' porque HttpParams es inmutable y necesitamos reasignarlo
    let params = new HttpParams()
      .set('status', status)
      .set('limit', limit.toString())
      .set('offset', offset.toString());

    // Si existe un término de búsqueda válido, se adjunta a los parámetros
    if (search) {
      params = params.set('search', search);
    }

    // Mantener el tipado estricto
    return this.http.get<ApiResponse<RequirementDashboard[]>>(this.coreApiUrl, { params });
  }

  /**
   * Solicitar el ticket temporal a Redis
   */
  requestDeletionTicket(specialKey: string): Observable<DeletionTicketResponse> {
    return this.http.post<DeletionTicketResponse>(`${this.coreApiUrl}/special-operations/validate-key`, {
      special_key: specialKey
    });
  }

  /**
   * Ejecutar el borrado lógico usando el ticket
   */
  softDeleteRequirement(id: string, ticket: string, justification: string): Observable<{success: boolean, message: string}> {
    return this.http.delete<{success: boolean, message: string}>(`${this.coreApiUrl}/${id}`, {
      body: {
        deletion_ticket: ticket,
        justification: justification
      }
    });
  } 
  
  // Valida el PIN y pide el ticket a Redis
  validateSpecialKey(pin: string) {
    return this.http.post(`${this.coreApiUrl}/special-operations/validate-key`, { pin });
  }

  // ----------------------------------------------------------------------
  // Configurar PIN (Onboarding)
  // ----------------------------------------------------------------------
  setupSpecialPin(loginPassword: string, newPin: string): Observable<{success: boolean, message: string}> {
    return this.http.post<{success: boolean, message: string}>(`${this.coreApiUrl}/special-operations/setup-pin`, {
      login_password: loginPassword,
      new_pin: newPin
    });
  }

  
  
}