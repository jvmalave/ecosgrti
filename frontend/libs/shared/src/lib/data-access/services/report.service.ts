import { Injectable, ProviderToken, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';

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
   * Solicita al backend la generación del Acta de Cierre o Documento de Seguimiento.
   * 
   * @param id El UUID del requerimiento
   */
  downloadRequirementReport(id: string): Observable<Blob> {
    return this.http.get(`${this.reportingApiUrl}/closure-act/${id}`, {
      responseType: 'blob' // CRÍTICO: Mantenemos la intercepción binaria
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
}