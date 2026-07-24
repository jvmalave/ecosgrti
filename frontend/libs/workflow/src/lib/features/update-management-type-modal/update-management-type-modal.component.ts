import { Component, inject, input, output, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import Swal from 'sweetalert2';
import { WorkflowApiService } from '../../data-access/services/atf.service';
import { NotificationService } from '../../data-access/services/notitication.services';


@Component({
  selector: 'lib-update-management-type-modal',
  standalone: true,
  imports: [ CommonModule, FormsModule],
  templateUrl: './update-management-type-modal.component.html',
  styleUrl: './update-management-type-modal.component.scss'
})
export class UpdateManagementTypeModalComponent implements OnInit {

  // --- INYECCIONES ---
  private workflowApi = inject(WorkflowApiService);
  private notificationService = inject(NotificationService);


  // --- INPUTS & OUTPUTS ---
  public requirementId = input.required<string>();
  public currentType = input.required<string>(); // Para preseleccionar el actual
  
  public closeModal = output<void>();
  public typeUpdated = output<{ tipo_gestion: string, progreso_global: number }>();

  // --- ESTADO REACTIVO (Signals) ---
  public selectedType = signal<string>('');
  public isSubmitting = signal<boolean>(false);

  // --- OPCIONES DISPONIBLES ---
  public managementOptions = [
    { id: 'ROLES', label: 'Roles', icon: 'bi-person-badge' },
    { id: 'ENTREGABLES', label: 'Entregables', icon: 'bi-files' },
    { id: 'MIXTO', label: 'Mixto (Roles + Entregables)', icon: 'bi-diagram-2' }
  ];

  ngOnInit(): void {
    // Preseleccionamos el valor actual al abrir el modal
    this.selectedType.set(this.currentType() || 'ROLES');
  }

  // --- MÉTODOS ---
  public selectType(typeId: string): void {
    if (this.isSubmitting()) return;
    this.selectedType.set(typeId);
  }

  public onSubmit(): void {
    if (this.selectedType() === this.currentType()) {
      // Si no hay cambios reales, cerramos sin hacer petición
      this.closeModal.emit();
      return;
    }

    this.isSubmitting.set(true);

    this.workflowApi.updateManagementType(this.requirementId(), this.selectedType()).subscribe({
      next: (response) => {
        this.isSubmitting.set(false);
        
        // Disparamos alerta rápida de éxito
        this.notificationService.toastSuccess('Tipo de gestión actualizado');

        // Emitimos los datos frescos hacia el padre para actualizar la UI reactivamente 
        this.typeUpdated.emit({
          tipo_gestion: response.tipo_gestion,
          progreso_global: response.progreso_global
        });
      },
      error: (error: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        console.error('Error al actualizar tipo de gestión:', error);
        this.notificationService.showError('Acción Denegada', error.error?.message || 'No se pudo actualizar el tipo de gestión.');
      }
    });
  }
}