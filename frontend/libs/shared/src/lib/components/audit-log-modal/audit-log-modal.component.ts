import { Component, effect, inject, signal, input, output, untracked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-audit-log-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './audit-log-modal.component.html'
})
export class AuditLogModalComponent {
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  logData = signal<any[]>([]);
  isLoading = signal<boolean>(false);
  isGeneratingPdf = signal<boolean>(false);

  // Filtros reactivos
  filterStartDate = signal<string>('');
  filterEndDate = signal<string>('');
  filterRrti = signal<string>('');
  filterAction = signal<string>('');

  readonly actionMap: Record<string, string> = {
    'LOGIN_SUCCESS': 'Inicio de Sesión',
    'LOGIN_FAIL': 'Intento Fallido',
    'LOGOUT': 'Cierre de Sesión',
    'CREATE_REQUIREMENT': 'Creación de Req.',
    'PASSWORD_CHANGE': 'Cambio Contraseña',
    'CREATE_ATF_AGREEMENT': 'Acuerdo ATF'
  };


  constructor() {
    effect(() => {
      if (this.isVisible()) {
        untracked(() => {
          this.initializeDefaultDates();
          this.loadData();
        });
      }
    });
  }

  closeModal(): void {
    this.closeModalEvent.emit();
  }

private initializeDefaultDates(): void {
    const today = new Date();
    const lastWeek = new Date();
    lastWeek.setDate(today.getDate() - 7);
    
    // Función para formatear la fecha a YYYY-MM-DD respetando la zona horaria local (GMT-4)
    const formatToLocalISO = (date: Date) => {
      const offset = date.getTimezoneOffset() * 60000;
      return new Date(date.getTime() - offset).toISOString().split('T')[0];
    };

    this.filterEndDate.set(formatToLocalISO(today));
    this.filterStartDate.set(formatToLocalISO(lastWeek));
    this.filterRrti.set('');
    this.filterAction.set('');
  }

  // private getPayload() {
  //   // Inicializamos solo con los parámetros obligatorios
  //   const payload: any = {
  //     start_date: this.filterStartDate(),
  //     end_date: this.filterEndDate(),
  //   };
    
  //   // Solo agregamos RRTI si el usuario escribió algo
  //   const rrtiVal = this.filterRrti() ? this.filterRrti().replace('#', '').trim() : '';
  //   if (rrtiVal !== '') {
  //     payload.rrti = rrtiVal;
  //   }
    
  //   // Solo agregamos Action si el usuario seleccionó una opción
  //   const actionVal = this.filterAction();
  //   if (actionVal !== '') {
  //     payload.action = actionVal;
  //   }
    
  //   return payload;
  // }

  private getPayload() {
    const payload = {
      start_date: this.filterStartDate(),
      end_date: this.filterEndDate(),
      rrti: this.filterRrti() ? this.filterRrti().replace('#', '').trim() : null,
      action: this.filterAction() ? this.filterAction() : null
    };
    
    return payload;
  }

  validateDates(): boolean {
    if (!this.filterStartDate() || !this.filterEndDate()) {
      this.notificationService.showWarning('Filtro Incompleto', 'Las fechas de inicio y fin son obligatorias.');
      return false;
    }
    if (new Date(this.filterStartDate()) > new Date(this.filterEndDate())) {
      this.notificationService.showWarning('Rango Inválido', 'La fecha de inicio no puede ser mayor a la final.');
      return false;
    }
    return true;
  }

  loadData(): void {
    if (!this.validateDates()) return;

    this.isLoading.set(true);
    this.reportService.getAuditLogData(this.getPayload()).subscribe({
      next: (res) => {
        if (res.success) this.logData.set(res.data);
        this.isLoading.set(false);
      },
      error: () => {
        this.notificationService.showError('Error', 'Fallo al sincronizar la bitácora.');
        this.isLoading.set(false);
      }
    });
  }

  downloadPdf(): void {
    if (!this.validateDates()) return;

    this.isGeneratingPdf.set(true);
    this.notificationService.showLoading('Generando Bitácora', 'Procesando historial de eventos...');

    this.reportService.downloadAuditLog(this.getPayload()).subscribe({
      next: (blob: Blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Bitacora_Auditoria_${new Date().getTime()}.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
        link.remove();
        
        this.isGeneratingPdf.set(false);
        this.notificationService.close();
        this.notificationService.showSuccess('¡Éxito!', 'Documento generado correctamente.');
      },
      error: () => {
        this.isGeneratingPdf.set(false);
        this.notificationService.close();
        this.notificationService.showError('Error', 'No se pudo generar el reporte PDF.');
      }
    });
  }
}