// frontend/libs/catalogs/src/lib/data-access/services/progress-matrix.service.ts

import { Injectable, inject, ProviderToken } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, Subject, tap } from 'rxjs';
import { 
  ProgressMatrix, 
  PublishMatrixPayload, 
  PublishResponse 
} from '../models/progress-matrix.model';

@Injectable({
  providedIn: 'root'
})
export class ProgressMatrixService {
  private readonly http = inject(HttpClient);
  
  // Inyección del token global de la API estandarizado
  private readonly globalApiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar la matriz
  private refreshSubject = new Subject<void>();
  
  // Construcción de la URL específica para el dominio workflow (matrices de progreso)
  private readonly catalogsApiUrl = `${this.globalApiUrl}/catalogs/progress-matrices`;

  /**
   * Observable para que los componentes se suscriban a los eventos de recarga.
   */
  get refresh$() {
    return this.refreshSubject.asObservable();
  }

  /**
   * Obtiene la versión activa de la matriz desde la caché o base de datos.
   * Ejecuta: GET /workflow/progress-matrices/active?type={managementType}
   * 
   * @param type Tipo de gestión ('ROLES' | 'ENTREGABLES' | 'MIXTO')
   * @returns Observable con la matriz V_activa
   */
  getActiveMatrix(type: string): Observable<ProgressMatrix> {
    const params = new HttpParams().set('type', type);
    return this.http.get<ProgressMatrix>(`${this.catalogsApiUrl}/active`, { params });
  }

  /**
   * Envía el payload para la creación inmutable de una nueva versión de matriz.
   * Ejecuta: POST /workflow/progress-matrices/publish
   * Notifica a los suscriptores mediante el refreshSubject tras una respuesta exitosa.
   * 
   * @param payload Array de hitos y tipo de gestión
   * @returns Observable con la confirmación de la V_nueva (201 Created)
   */
  publishMatrix(payload: PublishMatrixPayload): Observable<PublishResponse> {
    return this.http.post<PublishResponse>(`${this.catalogsApiUrl}/publish`, payload).pipe(
      // Disparamos la notificación de que se publicó una nueva matriz
      tap(() => {
        this.refreshSubject.next();
      })
    );
  }
}