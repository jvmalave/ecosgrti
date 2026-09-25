import { Component, inject, signal, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-tracking-document-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './tracking-document-modal.component.html'
})
export class TrackingDocumentModalComponent {
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  // Estados
  rrtiCode = signal<string>('');
  requirementData = signal<any>(null); // Almacena la data cuando se encuentra
  isSearching = signal<boolean>(false);
  isGeneratingPdf = signal<boolean>(false);

  readonly phaseDictionary: Record<string, string> = {
    'RC': 'Requerimiento Creado (Inicio)',
    'ES-R': 'Planificación Realizada',
    'ATF-I': 'Análisis Técnico Funcional Iniciado',
    'ATF-C': 'Análisis Técnico Funcional Concluido',
    'DT-I': 'Diseño Técnico Iniciado',
    'DT-C': 'Diseño Técnico Concluido',
    'COR-I': 'Construcción Roles Iniciada',
    'COR-C': 'Construcción Roles Concluida',
    'PI-I': 'Pruebas Integrales Iniciadas',
    'PI-C': 'Pruebas Integrales Concluidas',
    'CER-I': 'Certificación Roles Iniciada',
    'CER-C': 'Certificación Roles Concluida',
    'PAP-I': 'Pase a Producción Iniciado',
    'PAP-C': 'Pase a Producción Concluido',
    'AU-I': 'Asignación a Usuarios Iniciada',
    'AU-C': 'Asignación a Usuarios Concluida',
    'COE-I': 'Construcción Entregables Iniciada',
    'COE-C': 'Construcción Entregables Concluida',
    'CEE-I': 'Certificación Entregables Iniciada',
    'CEE-C': 'Certificación Entregables Concluida',
  };

  closeModal(): void {
    this.resetSearch();
    this.closeModalEvent.emit();
  }

  resetSearch(): void {
    this.rrtiCode.set('');
    this.requirementData.set(null);
  }

  searchRequirement(): void {
    const rrti = this.rrtiCode().trim();
    if (!rrti) {
      this.notificationService.showWarning('Dato Requerido', 'Por favor, ingrese un código RRTI.');
      return;
    }

    this.isSearching.set(true);
    
    this.reportService.getTrackingData(rrti).subscribe({
      next: (res) => {
        this.requirementData.set(res.data);
        this.isSearching.set(false);
      },
      error: (err) => {
        this.isSearching.set(false);
        if (err.status === 404) {
          this.notificationService.showError('No Encontrado', `El requerimiento ${rrti} no existe.`);
        } else {
          this.notificationService.showError('Error', 'Fallo al consultar el sistema.');
        }
      }
    });
  }

  downloadDocument(): void {
    const rrti = this.requirementData().rrti;
    this.isGeneratingPdf.set(true);
    this.notificationService.showLoading('Generando Documento', 'Renderizando el PDF oficial...');

    this.reportService.downloadTrackingDocument(rrti).subscribe({
      next: (blob: Blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        
        const prefix = this.requirementData().status === 'RF' ? 'acta_cierre' : 'seguimiento';
        link.download = `${prefix}_${rrti}.pdf`;
        
        link.click();
        window.URL.revokeObjectURL(url);
        link.remove();
        
        this.isGeneratingPdf.set(false);
        this.notificationService.close();
      },
      error: () => {
        this.isGeneratingPdf.set(false);
        this.notificationService.close();
        this.notificationService.showError('Error', 'No se pudo generar el documento PDF.');
      }
    });
  }
}