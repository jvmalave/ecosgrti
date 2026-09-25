import { Component, effect, inject, signal, input, output, untracked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms'; // Vital para el uso de ngModel
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-cspe-workload-modal',
  standalone: true,
  imports: [CommonModule, FormsModule], // No olvides inyectarlo aquí
  templateUrl: './cspe-workload-modal.component.html'
})
export class CspeWorkloadModalComponent {
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  consultantsWorkload = signal<any[]>([]);
  consultantHistory = signal<any>(null);
  
  isLoading = signal<boolean>(false);
  isViewingHistory = signal<boolean>(false);

  // NUEVO: Control del horizonte de tiempo y lectura de horas base
  selectedHorizon = signal<string>('current_week');
  periodBaseHours = signal<number>(40); 

  includeBacklog = signal<boolean>(false);

  constructor() {
    effect(() => {
      if (this.isVisible()) {
        untracked(() => {
          this.resetView();
          this.fetchWorkload();
        });
      }
    });
  }

  closeModal(): void {
    this.closeModalEvent.emit();
  }

  resetView(): void {
    this.isViewingHistory.set(false);
    this.consultantHistory.set(null);
  }

  // Ahora pasamos el horizonte seleccionado como parámetro
  fetchWorkload(): void {
    this.isLoading.set(true);
    this.reportService.getConsultantsWorkload(this.selectedHorizon(), this.includeBacklog()).subscribe({
      next: (res) => {
        if (res.success) {
          this.consultantsWorkload.set(res.data);
          if (res.data.length > 0) {
            this.periodBaseHours.set(res.data[0].base_hours || 40);
          }
        }
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.notificationService.showError('Error', 'Fallo al procesar la capacidad proyectada.');
      }
    });
  }

  // Nuevo método para cuando el usuario hace clic en el switch de atraso
  onBacklogToggle(): void {
    this.includeBacklog.set(!this.includeBacklog());
    this.fetchWorkload();
  }

  // Disparador cuando el usuario cambia el select en la interfaz
  onHorizonChange(newHorizon: string): void {
    this.selectedHorizon.set(newHorizon);
    this.fetchWorkload();
  }

  viewHistory(consultantId: string): void {
    this.isLoading.set(true);
    this.isViewingHistory.set(true);
    
    this.reportService.getConsultantHistory(consultantId).subscribe({
      next: (res) => {
        if (res.success) {
          this.consultantHistory.set(res.data);
        }
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.resetView();
        this.notificationService.showError('Error', 'No se pudo extraer el historial de operaciones.');
      }
    });
  }

  getProgressBarClass(percentage: number): string {
    if (percentage > 90) return 'bg-danger';
    if (percentage > 70) return 'bg-warning text-dark';
    return 'bg-success';
  }

  // NUEVO: Método semántico para colorear el avance del requerimiento
  getProgressBadgeClass(percentage: number): string {
    if (percentage >= 100) return 'bg-success';
    if (percentage >= 50) return 'bg-info text-dark';
    if (percentage > 0) return 'bg-primary';
    return 'bg-secondary';
  }
}