import { inject, Injectable, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';

// Importamos TODAS las interfaces desde nuestro archivo centralizado
import { 
  RequirementRole, 
  ActionResponse, 
  RoleResponse, 
  RolesListResponse 
} from '../models/requirement-role.model';

@Injectable({
  providedIn: 'root'
})
export class RequirementRoleService {
  private http = inject(HttpClient);
  private globalApiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar datos
  public refreshDashboard$ = new Subject<void>();
  
  // Construcción de URLs específicas según el dominio y la inmutabilidad del vínculo
  private workflowReqApiUrl = `${this.globalApiUrl}/workflow/requirements`;
  private workflowRolesApiUrl = `${this.globalApiUrl}/workflow/roles`;

  /**
   * Obtiene la lista de roles de un requerimiento específico
   */
  public getRoles(requirementId: string): Observable<RolesListResponse> {
    return this.http.get<RolesListResponse>(`${this.workflowReqApiUrl}/${requirementId}/components-data`);
  }

  /**
   * Registra un nuevo rol (CU-019)
   */
  public createRole(requirementId: string, payload: Partial<RequirementRole>): Observable<RoleResponse> {
    return this.http.post<RoleResponse>(`${this.workflowReqApiUrl}/${requirementId}/roles`, payload);
  }

  /**
   * Actualiza un rol existente (CU-021) 
   * Respeta la Inmutabilidad del Vínculo atacando directamente al endpoint del rol
   */
  public updateRole(roleId: string, payload: Partial<RequirementRole>): Observable<RoleResponse> {
    return this.http.put<RoleResponse>(`${this.workflowRolesApiUrl}/${roleId}`, payload);
  }

  /**
   * Elimina un rol
   */
  public deleteRole(roleId: string): Observable<ActionResponse> {
    return this.http.delete<ActionResponse>(`${this.workflowRolesApiUrl}/${roleId}`);
  }
}