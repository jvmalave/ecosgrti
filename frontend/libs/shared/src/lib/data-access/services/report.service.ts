import { Injectable, ProviderToken, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { OtdResponse, DeviationResponse, AgingResponse, KpiFilters } from '../../models/kpi-metrics.interface';


@Injectable({
  providedIn: 'root'
})
export class ReportService {

  private http = inject(HttpClient);
  
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  
  // Subject para notificar a otros componentes que deben recargar datos
  public refreshDashboard$ = new Subject<void>();
  
  // Construcción de la URL específica para este dominio
  private reportingApiUrl = `${this.apiUrl}/reports`;


  /**
   * Solicita al backend la generación del reporte de prueba.
   */
  downloadTestReport(): Observable<Blob> {
    return this.http.get(`${this.reportingApiUrl}/test`, {
      responseType: 'blob' // CRÍTICO: Indica que recibiremos un archivo binario
    });
  }

  downloadMdmDirectoryReport(): Observable<Blob> {
    return this.http.get(`${this.reportingApiUrl}/mdm-directory`, {
      responseType: 'blob' // CRÍTICO: Mantenemos la intercepción binaria
    });
  }

  /**
   * Obtiene la data del Directorio MDM para renderizar en tabla
   */
  getMdmDirectoryData(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/mdm-directory/data`);
  }


  /**
   * Solicita al backend la generación del Acta de Cierre o Documento de Seguimiento.
   * 
   * @param id El UUID del requerimiento
   */
  downloadRequirementReport(id: string): Observable<Blob> {
    return this.http.get(`${this.reportingApiUrl}/closure-act/${id}`, {
      responseType: 'blob' // CRÍTICO: Mantenemos la intercepción binaria
    });
  }


  downloadAuditLog(filters: { start_date: string, end_date: string, rrti: string, action: string }): Observable<Blob> {
    let params = new HttpParams()
      .set('start_date', filters.start_date)
      .set('end_date', filters.end_date);

    if (filters.rrti) {
      params = params.set('rrti', filters.rrti);
    }
    if (filters.action) {
      params = params.set('action', filters.action);
    }

    return this.http.get(`${this.reportingApiUrl}/audit-log`, { 
      params,
      responseType: 'blob'
    });
  }

  downloadConsultantManagement(filters: any): Observable<Blob> {
    let params = new HttpParams();

    if (filters.start_date && filters.end_date) {
      params = params.set('start_date', filters.start_date).set('end_date', filters.end_date);
    }
    if (filters.rrti) params = params.set('rrti', filters.rrti);
    if (filters.status_type) params = params.set('status_type', filters.status_type);
    
    // Inyectamos el ID y el Nombre
    if (filters.consultant_id) params = params.set('consultant_id', filters.consultant_id);
    if (filters.consultant_name) params = params.set('consultant_name', filters.consultant_name);

    return this.http.get(`${this.reportingApiUrl}/consultant-management`, { params, responseType: 'blob' });
  }

  // 1. Método para obtener el diccionario
  getCspeConsultantsList(): Observable<{id: string, name: string}[]> {
    return this.http.get<{id: string, name: string}[]>(`${this.reportingApiUrl}/cspe-consultants`);
  }

  downloadProductionDeployments(filters: any): Observable<Blob> {
    let params = new HttpParams();

    if (filters.start_date && filters.end_date) {
      params = params.set('start_date', filters.start_date).set('end_date', filters.end_date);
    }
    if (filters.rrti) {
      params = params.set('rrti', filters.rrti);
    }

    return this.http.get(`${this.reportingApiUrl}/production-deployments`, {
      params,
      responseType: 'blob'
    });
  }

  /**
   * Utilidad maestra para forzar la descarga nativa de cualquier Blob en el navegador.
   * @param blob El flujo de datos binarios
   * @param filename El nombre con el que se guardará el archivo
   */
  forceFileDownload(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    
    // Anexamos el enlace al DOM, hacemos clic y lo limpiamos
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    
    // Liberamos la memoria
    window.URL.revokeObjectURL(url);
  }
  /**
   * Solicita el reporte consultando directamente por el código RRTI.
   */
  downloadReportByRrti(rrti: string): Observable<Blob> {
    return this.http.get(`${this.reportingApiUrl}/closure-act/rrti/${rrti}`, {
      responseType: 'blob'
    });
  }

  // Descarga la Sábana Operativa en formato CSV/Excel
  downloadOperationalSheet(filters: any): Observable<Blob> {
    let params = new HttpParams();
    if (filters.start_date) params = params.set('start_date', filters.start_date);
    if (filters.end_date) params = params.set('end_date', filters.end_date);

    return this.http.get(`${this.reportingApiUrl}/operational-sheet`, {
      params,
      responseType: 'blob'
    });
  }

  /**
   * Construye los HttpParams a partir de un objeto de filtros.
   */
  private buildParams(filters?: KpiFilters): HttpParams {
    let params = new HttpParams();
    if (filters?.start_date) {
      params = params.set('start_date', filters.start_date);
    }
    if (filters?.end_date) {
      params = params.set('end_date', filters.end_date);
    }
    return params;
  }

  /**
   * Obtiene las métricas de Tasa de Entrega a Tiempo (OTD).
   */
  getOtdMetrics(filters?: KpiFilters): Observable<OtdResponse> {
    return this.http.get<OtdResponse>(`${this.reportingApiUrl}/kpi/otd`, {
      params: this.buildParams(filters)
    });
  }

  /**
   * Obtiene las estadísticas de desviación y alertas tempranas en tiempo real.
   */
  getDeviationAlerts(): Observable<DeviationResponse> {
    // Este endpoint evalúa contra el reloj actual, no requiere parámetros de fecha
    return this.http.get<DeviationResponse>(`${this.reportingApiUrl}/kpi/deviation`);
  }

  /**
   * Obtiene el análisis de envejecimiento y detección de cuellos de botella.
   */
  getAgingMetrics(): Observable<AgingResponse> {
    // Endpoint evaluado en tiempo real, sin filtros de fecha
    return this.http.get<AgingResponse>(`${this.reportingApiUrl}/kpi/aging`);
  }

  /**
   * Descarga el Resumen Ejecutivo Integral en formato PDF.
   * Utiliza el tipo 'blob' para manejar correctamente el archivo binario.
   */
  downloadExecutiveSummary(filters?: KpiFilters): Observable<Blob> {
    return this.http.get(`${this.reportingApiUrl}/executive-summary`, {
      params: this.buildParams(filters),
      responseType: 'blob' // CRÍTICO: Define la recepción de un archivo binario
    });
  }

  

  

  
  

  

  




}