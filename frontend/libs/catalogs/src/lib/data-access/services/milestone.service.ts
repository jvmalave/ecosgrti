// frontend/libs/catalogs/src/lib/data-access/services/milestone.service.ts

import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { Milestone } from '../models/milestone.model';


@Injectable({
  providedIn: 'root'
})
export class MilestoneService {
  private readonly http = inject(HttpClient);

  // INYECCION DEL TOKEN GLOBAL DE LA API
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // SUBJECT PARA NOTIFICAR A OTROS COMPONENTES QUE DEBEN RECARGAR DATOS
  private refreshSubject = new Subject<void>();
  
  // CONSTRUCCION DE LA URL ESPECIFICA PARA EL DOMINIO DE CATÁLOGOS HITOS TÉCNICOS
  private readonly milestonesApiUrl = `${this.apiUrl}/catalogs/milestones`;

  // OBTIENE LA LISTA DE HITOS TÉCNICOS FILTRADOS OPCIONALMENTE POR TIPO DE GESTIÓN
  public getMilestones(managementType?: string): Observable<Milestone[]> {
    let params = new HttpParams();
    if (managementType) {
      params = params.set('management_type', managementType);
    }
    
    // CORRECCIÓN: Se reemplazó this.apiUrl por this.milestonesApiUrl
    return this.http.get<Milestone[]>(this.milestonesApiUrl, { params });
  }

  // REGISTRAR UN NUEVO HITO TÉCNICO
  public createMilestone(milestone: Milestone): Observable<{ message: string; id: string }> {
    return this.http.post<{ message: string; id: string }>(this.milestonesApiUrl, milestone);
  }

  // ACTUALIZAR UN HITO TÉCNICO
  public updateMilestone(id: string, milestone: Milestone): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.milestonesApiUrl}/${id}`, milestone);
  }

  // ELIMINAR UN HITO TÉCNICO
  public deleteMilestone(id: string): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.milestonesApiUrl}/${id}`);
  }
}