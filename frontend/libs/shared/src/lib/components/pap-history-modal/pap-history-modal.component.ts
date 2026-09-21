import { Component, effect, inject, input, output, signal, untracked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-pap-history-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './pap-history-modal.component.html'
})
export class PapHistoryModalComponent {
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  // Estados de datos
  deployments = signal<any[]>([]);
  isLoading = signal<boolean>(false);
  hasSearched = signal<boolean>(false);

  // Filtros
  startDate = signal<string>('');
  endDate = signal<string>('');
  rrti = signal<string>('');

  constructor() {
    effect(() => {
      if (this.isVisible()) {
        untracked(() => {
          // Limpiamos al abrir
          this.resetFilters();
          this.deployments.set([]);
          this.hasSearched.set(false);
          // Opcional: Cargar todo por defecto al abrir descomentando la siguiente línea
          // this.fetchData(); 
        });
      }
    });
  }

  closeModal(): void {
    this.closeModalEvent.emit();
  }

  resetFilters(): void {
    this.startDate.set('');
    this.endDate.set('');
    this.rrti.set('');
  }

  fetchData(): void {
    if ((this.startDate() && !this.endDate()) || (!this.startDate() && this.endDate())) {
      this.notificationService.showError('Validación', 'Debe completar el rango de fechas.');
      return;
    }

    this.isLoading.set(true);
    this.hasSearched.set(true);

    const filters = {
      start_date: this.startDate(),
      end_date: this.endDate(),
      rrti: this.rrti().trim()
    };

    this.reportService.getProductionDeploymentsData(filters).subscribe({
      next: (res) => {
        if (res.success) {
          this.deployments.set(res.data);
        }
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.notificationService.showError('Error', 'Fallo al consultar el histórico PAP.');
      }
    });
  }

  exportToPdf(): void {
    this.notificationService.showLoading('Procesando Documento', 'Generando el PDF de Pases a Producción...');
    const filters = {
      start_date: this.startDate(),
      end_date: this.endDate(),
      rrti: this.rrti().trim()
    };

    this.reportService.downloadProductionDeployments(filters).subscribe({
      next: (blob: Blob) => {
        const filename = `Pases_Produccion_${new Date().getTime()}.pdf`;
        this.reportService.forceFileDownload(blob, filename);
        this.notificationService.close();
      },
      error: (err) => {
        this.notificationService.showError('Error', 'No se pudo generar el documento PDF.');
      }
    });
  }
}
