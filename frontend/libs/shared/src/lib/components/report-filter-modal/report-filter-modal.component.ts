import { Component, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-report-filter-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './report-filter-modal.component.html'
})
export class ReportFilterModalComponent {
  private notificationService = new NotificationService(); // O inyéctalo en el constructor

  isVisible = input<boolean>(false);
  consultants = input<{id: string, name: string}[]>([]);
  
  // 'consultant_history' o 'consolidated' para cambiar el título dinámicamente
  reportType = input<string>('consultant_history'); 

  closeModalEvent = output<void>();
  generateReportEvent = output<any>();

  // Estado del formulario
  startDate = signal<string>('');
  endDate = signal<string>('');
  consultantId = signal<string>('');
  rrti = signal<string>('');
  statusType = signal<string>('');
  format = signal<'pdf' | 'csv'>('pdf');

  closeModal(): void {
    this.resetForm();
    this.closeModalEvent.emit();
  }

  resetForm(): void {
    this.startDate.set('');
    this.endDate.set('');
    this.consultantId.set('');
    this.rrti.set('');
    this.statusType.set('');
    this.format.set('pdf');
  }

  onSubmit(): void {
    // Validación: Si hay una fecha, debe estar la otra
    if ((this.startDate() && !this.endDate()) || (!this.startDate() && this.endDate())) {
      this.notificationService.showError('Validación', 'Debe completar el rango ingresando fecha de inicio y fin.');
      return;
    }

    // Buscamos el nombre del consultor seleccionado para mandarlo al PDF
    const selectedConsultant = this.consultants().find(c => c.id === this.consultantId());
    const consultantName = selectedConsultant ? selectedConsultant.name : '';

    const filters = {
      start_date: this.startDate(),
      end_date: this.endDate(),
      rrti: this.rrti().trim(),
      status_type: this.statusType(),
      consultant_id: this.consultantId(),
      consultant_name: consultantName,
      format: this.format()
    };

    this.generateReportEvent.emit(filters);
    this.resetForm();
  }
}