import { Component, effect, inject, input, output, signal, untracked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-operational-data-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './operational-data-modal.component.html'
})
export class OperationalDataModalComponent {
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  startDate = signal<string>('');
  endDate = signal<string>('');
  isLoading = signal<boolean>(false);

  constructor() {
    effect(() => {
      if (this.isVisible()) {
        untracked(() => {
          this.startDate.set('');
          this.endDate.set('');
        });
      }
    });
  }

  closeModal(): void {
    this.closeModalEvent.emit();
  }

  downloadCsv(): void {
    if ((this.startDate() && !this.endDate()) || (!this.startDate() && this.endDate())) {
      this.notificationService.showError('Validación', 'Debe completar el rango de fechas o dejar ambos en blanco.');
      return;
    }

    this.isLoading.set(true);
    const filters = { start_date: this.startDate(), end_date: this.endDate() };

    this.notificationService.showLoading('Extrayendo Data', 'Generando la sábana operativa en formato CSV...');

    this.reportService.downloadOperationalSheet(filters).subscribe({
      next: (blob: Blob) => {
        const filename = `Sabana_Operativa_CSPE_${new Date().getTime()}.csv`;
        this.reportService.forceFileDownload(blob, filename);
        this.notificationService.close();
        this.isLoading.set(false);
        this.closeModal(); 
      },
      error: (err) => {
        console.error('Error generando sábana:', err);
        this.notificationService.showError('Error de Extracción', 'No se pudo generar el archivo CSV.');
        this.isLoading.set(false);
      }
    });
  }
}