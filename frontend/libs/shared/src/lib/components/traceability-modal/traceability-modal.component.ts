import { Component, input, output, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-traceability-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './traceability-modal.component.html',
  styleUrls: ['./traceability-modal.component.scss']
})
export class TraceabilityModalComponent {
  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  searchTerm = signal<string>('');
  isLoading = signal<boolean>(false);
  results = signal<any[]>([]);
  expandedRrti = signal<string | null>(null);

  closeModal(): void {
    this.closeModalEvent.emit();
    this.searchTerm.set('');
    this.results.set([]);
    this.expandedRrti.set(null);
  }

  searchComponent(): void {
    if (this.searchTerm().trim().length < 3) {
      this.notificationService.showError('Búsqueda', 'Ingrese al menos 3 caracteres.');
      return;
    }

    this.isLoading.set(true);
    this.reportService.searchComponentTraceability(this.searchTerm().trim()).subscribe({
      next: (res) => {
        const data = res.data || [];
        
        // LÓGICA DE AGRUPACIÓN: Unificar fases consecutivas (Ej: múltiples DT o COR)
        const processedData = data.map((item: any) => {
          const grouped: any[] = [];
          let currentGroup: any = null;

          item.timeline.forEach((event: any) => {
            if (currentGroup && currentGroup.phase === event.phase) {
              currentGroup.entries.push(event);
            } else {
              currentGroup = {
                phase: event.phase,
                icon: event.icon,
                color: event.color,
                entries: [event]
              };
              grouped.push(currentGroup);
            }
          });

          return { ...item, groupedTimeline: grouped };
        });

        this.results.set(processedData);
        
        if (processedData.length === 0) {
          this.notificationService.showError('Sin resultados', 'No se encontró trazabilidad para este componente.');
        } else if (processedData.length === 1) {
          this.expandedRrti.set(processedData[0].rrti);
        } else {
          this.expandedRrti.set(null);
        }
        
        this.isLoading.set(false);
      },
      error: (err) => {
        console.error(err);
        this.notificationService.showError('Error', 'No se pudo completar la búsqueda.');
        this.isLoading.set(false);
      }
    });
  }

  // Método auxiliar para limpiar el nombre del archivo de la ruta
  getFileName(path: string): string {
    if (!path) return 'Documento_Adjunto';
    return path.split('/').pop() || 'Documento_Adjunto';
  }

  // Nuevo método de visualización en pestaña (sin forzar descarga)
  viewSupport(filePath: string): void {
    if (!filePath) return;

    this.notificationService.showLoading('Abriendo', 'Preparando documento de soporte...');

    this.reportService.downloadSupportFile(filePath).subscribe({
      next: (blob: Blob) => {
        // Crear URL en memoria para visualizar
        const fileURL = URL.createObjectURL(blob);
        window.open(fileURL, '_blank');
        this.notificationService.close();

        // Liberar memoria del navegador después de abrirlo
        setTimeout(() => URL.revokeObjectURL(fileURL), 10000);
      },
      error: (err) => {
        console.error('Error visualizando soporte:', err);
        this.notificationService.close();
        if (err.status === 404) {
          this.notificationService.showError('Archivo no encontrado', 'El soporte físico ya no existe en el servidor.');
        } else {
          this.notificationService.showError('Error', 'No se pudo abrir el archivo de soporte.');
        }
      }
    });
  }

  toggleAccordion(rrti: string): void {
    this.expandedRrti.update(current => current === rrti ? null : rrti);
  }

  downloadSupport(filePath: string): void {
    if (!filePath) return;

    this.notificationService.showLoading('Descargando', 'Obteniendo archivo de soporte...');

    this.reportService.downloadSupportFile(filePath).subscribe({
      next: (blob: Blob) => {
        // Extraemos el nombre original del archivo desde la ruta (ej. 'documento.pdf')
        const filename = filePath.split('/').pop() || 'soporte_trazabilidad.pdf';
        
        // Reutilizamos tu método utilitario para descargar Blobs
        this.reportService.forceFileDownload(blob, filename);
        
        this.notificationService.close();
      },
      error: (err) => {
        console.error('Error descargando soporte:', err);
        this.notificationService.close();
        
        // Manejo de error si el archivo fue eliminado físicamente del disco
        if (err.status === 404) {
          this.notificationService.showError('Archivo no encontrado', 'El soporte físico ya no existe en el servidor.');
        } else {
          this.notificationService.showError('Error de Descarga', 'No se pudo obtener el archivo de soporte.');
        }
      }
    });
  }

  downloadPdf(item: any): void {
    if (!item) return;

    this.notificationService.showLoading('Generando PDF', `Construyendo expediente para ${item.component_name}...`);

    this.reportService.exportComponentPdf(item).subscribe({
      next: (blob: Blob) => {
        this.reportService.forceFileDownload(blob, `Expediente_${item.component_name}_RRTI_${item.rrti}.pdf`);
        this.notificationService.close();
        this.notificationService.toastSuccess('PDF generado exitosamente');
      },
      error: (err) => {
        console.error('Error generando PDF:', err);
        this.notificationService.close();
        this.notificationService.showError('Error', 'No se pudo generar el expediente PDF.');
      }
    });
  }
}