import { Component, effect, inject, input, output, signal, untracked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartData } from 'chart.js';
import { forkJoin } from 'rxjs';
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';
import { AlertsModalComponent } from '../alerts-modal/alerts-modal.component';

@Component({
  selector: 'lib-kpi-modal',
  standalone: true,
  imports: [CommonModule, FormsModule, BaseChartDirective, AlertsModalComponent],
  templateUrl: './kpi-modal.component.html'
})
export class KpiModalComponent {
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  isLoadingGlobal = signal<boolean>(false);
  isLoadingPeriod = signal<boolean>(false);
  
  startDate = signal<string>('');
  endDate = signal<string>('');

  globalOtdPercentage = signal<number>(0);
  uniqueCriticalRrtisCount = signal<number>(0);
  isAlertsModalOpen = false;

  // --- ZONA 1: SALUD GLOBAL (Inventario / Sin fechas) ---
  activeReqs = signal<number>(0);
  avgGlobalAge = signal<number>(0);
  criticalAlerts = signal<any[]>([]);
  globalTotalCreated = signal<number>(0);
  globalTotalClosed = signal<number>(0);
  globalOnTimeCount = signal<number>(0);
  globalTotalEvaluated = signal<number>(0);
  globalTotalComponents = signal<number>(0);
  globalRoles = signal<number>(0);
  globalDeliverables = signal<number>(0);

  agingChartOptions: ChartConfiguration['options'] = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };
  agingChartData: ChartData<'bar'> = { labels: [], datasets: [] };

  statusChartOptions: ChartConfiguration['options'] = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, indexAxis: 'y' };
  statusChartData: ChartData<'bar'> = { labels: [], datasets: [] };

  // --- ZONA 2: RENDIMIENTO OPERATIVO (Flujo / Con fechas) ---
  periodEntered = signal<number>(0);
  periodClosed = signal<number>(0);
  otdPercentage = signal<number>(0);
  periodTotalComponents = signal<number>(0);
  periodRoles = signal<number>(0);
  periodDeliverables = signal<number>(0);

  // otdChartOptions: ChartConfiguration['options'] = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } };

  otdChartOptions: ChartConfiguration<'doughnut'>['options'] = { 
    responsive: true, 
    maintainAspectRatio: false, 
    rotation: 180,
    plugins: { legend: { position: 'right' } } 
  };

  otdChartData: ChartData<'doughnut'> = { labels: [], datasets: [] };

  constructor() {
    effect(() => {
      if (this.isVisible()) {
        untracked(() => {
          this.fetchGlobalHealth();
          this.fetchPeriodPerformance();
        });
      }
    });
  }

  closeModal(): void { this.closeModalEvent.emit(); }

  // Carga la foto actual de la coordinación (Ignora las fechas)
  fetchGlobalHealth(): void {
    this.isLoadingGlobal.set(true);
    
    forkJoin({
      aging: this.reportService.getAgingMetrics(),
      deviations: this.reportService.getDeviationMetrics(),
      globalOps: this.reportService.getOperationalMetrics({}), 
      globalOtd: this.reportService.getOtdMetrics({}) // NUEVO: OTD Histórico
    }).subscribe({
      next: (res) => {
        this.activeReqs.set(res.aging.data.summary.total_open_requirements);
        this.avgGlobalAge.set(res.aging.data.summary.average_global_age);
        this.globalOtdPercentage.set(res.globalOtd.data.otd_percentage); // Mapeo OTD Global
        this.globalTotalCreated.set(res.globalOps.data.total_requirements);
        this.globalTotalClosed.set(res.globalOps.data.completed_count);
        this.globalOnTimeCount.set(res.globalOtd.data.on_time_count);
        this.globalTotalEvaluated.set(res.globalOtd.data.total_evaluated);

        if (res.globalOps.data.components) {
          this.globalTotalComponents.set(res.globalOps.data.components.total);
          this.globalRoles.set(res.globalOps.data.components.roles);
          this.globalDeliverables.set(res.globalOps.data.components.deliverables);
        }
              
        // Filtramos las alertas críticas (> 5 días)
        const allAlerts = res.deviations.data.active_alerts || [];
        const critical = allAlerts.filter((alert: any) => alert.days_late > 5);
        this.criticalAlerts.set(critical);

        // Extraemos los RRTI únicos para la tarjeta visual
        const uniqueRrtis = new Set(critical.map((a: any) => a.rrti));
        this.uniqueCriticalRrtisCount.set(uniqueRrtis.size);

        // Gráfica de Aging
        const buckets = res.aging.data.aging_buckets;
        this.agingChartData = {
          labels: ['0-15 Días', '16-30 Días', '31-60 Días', '+60 Días'],
          datasets: [{ label: 'Requerimientos', data: [buckets['0_15_days'], buckets['16_30_days'], buckets['31_60_days'], buckets['over_60_days']], backgroundColor: ['#3B82F6', '#F59E0B', '#F97316', '#EF4444'] }]
        };

        
        // Gráfica de Estatus Global
        const statusLabels = Object.keys(res.globalOps.data.by_status);
        const statusData = Object.values(res.globalOps.data.by_status) as number[];
        
        // Mapeo dinámico de colores: Verde para finalizados, azul para el resto
        const dynamicColors = statusLabels.map(label => 
          label.toLowerCase().includes('finalizado') ? '#10B981' : '#0056b3'
        );

        this.statusChartData = {
          labels: statusLabels,
          datasets: [{ 
            label: 'Requerimientos', 
            data: statusData, 
            backgroundColor: dynamicColors // <--- Usamos el arreglo de colores aquí
          }]
        };

        this.isLoadingGlobal.set(false);
      },
      error: () => {
        this.isLoadingGlobal.set(false);
        this.notificationService.showError('Error', 'No se pudo cargar la salud global.');
      }
    });
  }

  // Carga el rendimiento del equipo en el lapso seleccionado
  fetchPeriodPerformance(): void {
    this.isLoadingPeriod.set(true);
    const filters = { start_date: this.startDate(), end_date: this.endDate() };

    forkJoin({
      otd: this.reportService.getOtdMetrics(filters),
      periodOps: this.reportService.getOperationalMetrics(filters)
    }).subscribe({
      next: (res) => {
        // Tarjetas del Período
        this.periodEntered.set(res.periodOps.data.total_requirements);
        this.periodClosed.set(res.periodOps.data.completed_count);
        this.otdPercentage.set(res.otd.data.otd_percentage);

        if (res.periodOps.data.components) {
          this.periodTotalComponents.set(res.periodOps.data.components.total);
          this.periodRoles.set(res.periodOps.data.components.roles);
          this.periodDeliverables.set(res.periodOps.data.components.deliverables);
        }

        // Gráfica OTD
        this.otdChartData = {
          labels: ['A Tiempo', 'Atrasado'],
          datasets: [{ data: [res.otd.data.on_time_count, res.otd.data.late_count], backgroundColor: ['#10B981', '#EF4444'] }]
        };

        this.isLoadingPeriod.set(false);
      },
      error: () => {
        this.isLoadingPeriod.set(false);
        this.notificationService.showError('Error', 'No se pudo calcular el rendimiento del período.');
      }
    });
  }

  downloadPdf(): void {
    this.notificationService.showLoading('Generando PDF', 'Consolidando Resumen Ejecutivo...');
    const filters = { start_date: this.startDate(), end_date: this.endDate() };
    this.reportService.downloadExecutiveSummary(filters).subscribe({
      next: (blob: Blob) => {
        const filename = `Resumen_Ejecutivo_${new Date().getTime()}.pdf`;
        this.reportService.forceFileDownload(blob, filename);
        this.notificationService.close();
      },
      error: () => this.notificationService.showError('Error', 'Fallo la generación del PDF.')
    });
  }
}