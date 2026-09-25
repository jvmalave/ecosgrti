import { Component, effect, inject, signal, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { forkJoin, finalize } from 'rxjs';
import Swal from 'sweetalert2';
import { ReportService } from '../../data-access/services/report.service';
import { 
  OtdResponse, 
  DeviationResponse, 
  AgingResponse 
} from '../../models/kpi-metrics.interface';

@Component({
  selector: 'lib-kpi-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './kpi-modal.component.html',
  styleUrls: ['./kpi-modal.component.scss']
})
export class KpiModalComponent {
  private reportService = inject(ReportService);

  // 1. Transformamos los @Input y @Output tradicionales a Signals (Angular 17.1+)
  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  // 2. Signals de estado
  otdMetrics = signal<OtdResponse['data'] | null>(null);
  deviationMetrics = signal<DeviationResponse['data'] | null>(null);
  agingMetrics = signal<AgingResponse['data'] | null>(null);
  isLoading = signal<boolean>(true);

  constructor() {
    // 3. LA MAGIA DE SIGNALS: effect()
    // Angular rastrea que estamos leyendo this.isVisible(). 
    // Cada vez que cambie a true, ejecutará loadKpiData() automáticamente.
    effect(() => {
      if (this.isVisible()) {
        this.loadKpiData();
      }
    });
  }

  closeModal(): void {
    // Emitimos el evento hacia el componente padre
    this.closeModalEvent.emit();
  }

  private loadKpiData(): void {
    this.isLoading.set(true);

    forkJoin({
      otd: this.reportService.getOtdMetrics(),
      deviation: this.reportService.getDeviationAlerts(),
      aging: this.reportService.getAgingMetrics()
    }).pipe(
      finalize(() => this.isLoading.set(false))
    ).subscribe({
      next: (responses) => {
        if (responses.otd.success) this.otdMetrics.set(responses.otd.data);
        if (responses.deviation.success) this.deviationMetrics.set(responses.deviation.data);
        if (responses.aging.success) this.agingMetrics.set(responses.aging.data);
      },
      error: () => {
        Swal.fire('Error de Conexión', 'No se pudieron sincronizar las métricas KPI.', 'error');
      }
    });
  }

  downloadReport(): void {
    Swal.fire({
      title: 'Generando Resumen Ejecutivo',
      html: 'Procesando métricas y renderizando gráficos...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    this.reportService.downloadExecutiveSummary().subscribe({
      next: (blob: Blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Resumen_Ejecutivo_ECOSGRTI_${new Date().getTime()}.pdf`;
        link.click();
        
        window.URL.revokeObjectURL(url);
        link.remove();

        Swal.fire({
          icon: 'success',
          title: '¡Descarga Exitosa!',
          text: 'El Resumen Ejecutivo ha sido generado correctamente.',
          confirmButtonColor: '#1c1ce2'
        });
      },
      error: (err) => {
        console.error('Error al descargar PDF:', err);
        Swal.fire({
          icon: 'error',
          title: 'Fallo en la Generación',
          text: 'Ocurrió un problema al construir el documento PDF.',
          confirmButtonColor: '#e91e63'
        });
      }
    });
  }
}