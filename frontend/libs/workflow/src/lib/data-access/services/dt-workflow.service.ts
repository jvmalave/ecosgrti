import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { 
  DtRoleInitResponse, 
  DtRoleStatusChangeResponse, 
  DtRegister, 
  ActionMessageResponse 
} from '../models/dt.model';

@Injectable({
  providedIn: 'root'
})
export class DtWorkflowService {
  private http = inject(HttpClient);
  
  // Inyección estricta de la URL global
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar datos
  public refreshDashboard$ = new Subject<void>();
  
  // URL base para las rutas del Diseño Técnico
  private workflowApiUrl = `${this.apiUrl}/workflow`;

  /**
   * Acceder a Gestión de Diseño Técnico (DT) y sincronizar roles
   */
  getRolesInit(requirementId: string): Observable<DtRoleInitResponse> {
    return this.http.get<DtRoleInitResponse>(`${this.workflowApiUrl}/requirements/${requirementId}/dt/roles-init`);
  }

  /**
   * Gestionar Ciclo de Vida del Rol en DT (Cerrar/Activar)
   */
  changeRoleStatus(roleId: string, action: 'CLOSE' | 'REOPEN'): Observable<DtRoleStatusChangeResponse> {
    return this.http.patch<DtRoleStatusChangeResponse>(`${this.workflowApiUrl}/dt/roles/${roleId}/status`, { action });
  }

  /**
   * Consultar Lista de Registros por Rol
   */
  getRegisters(roleId: string): Observable<DtRegister[]> {
    return this.http.get<DtRegister[]>(`${this.workflowApiUrl}/dt/roles/${roleId}/registers`);
  }

  /**
   * Agregar Registro de rol de Diseño Técnico
   */
  storeRegister(requirementId: string, roleId: string, data: Partial<DtRegister>): Observable<DtRegister> {
    return this.http.post<DtRegister>(`${this.workflowApiUrl}/requirements/${requirementId}/dt/roles/${roleId}/registers`, data);
  }

  /**
   * Actualizar Registro de rol de Diseño Técnico
   */
  updateRegister(registerId: string, data: Partial<DtRegister>): Observable<DtRegister> {
    return this.http.put<DtRegister>(`${this.workflowApiUrl}/dt/registers/${registerId}`, data);
  }

  /**
   * Eliminar Registro de rol de Diseño (Físico)
   */
  deleteRegister(registerId: string): Observable<ActionMessageResponse> {
    return this.http.delete<ActionMessageResponse>(`${this.workflowApiUrl}/dt/registers/${registerId}`);
  }
}