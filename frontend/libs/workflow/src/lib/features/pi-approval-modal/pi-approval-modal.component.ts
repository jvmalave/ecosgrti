import { Component, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { WorkflowPhaseService } from '../../data-access/services/workflow-phase.service';
import { NotificationService } from '../../data-access/services/notification.services';

@Component({
  selector: 'lib-pi-approval-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './pi-approval-modal.component.html',
  styleUrls: ['./pi-approval-modal.component.scss']
})
export class PiApprovalModalComponent {
  // Inputs desde el modal orquestador
  requirementId = input.required<string>();
  roleId = input.required<string>();
  roleName = input.required<string>();
  rrti = input.required<string>();
  consultantName = input<string>('Consultor Funcional');
  functionalUnit = input<string>('Unidad Solicitante');

  // Output para cerrar
  closeModal = output<void>();

  // Inyecciones
  private readonly fb = inject(FormBuilder);
  private readonly phaseService = inject(WorkflowPhaseService);
  private readonly notificationService = inject(NotificationService);

  // Estados Reactivos
  selectedFile = signal<File | null>(null);
  isSubmitting = signal<boolean>(false);

  // Formulario para los campos de texto
  approvalForm: FormGroup = this.fb.group({});

  /**
   * Captura y valida el archivo seleccionado en el cliente
   */
  onFileSelected(event: Event): void {
    const inputElement = event.target as HTMLInputElement;
    
    if (inputElement.files && inputElement.files.length > 0) {
      const file = inputElement.files[0];

      // 1. Validar Tipo (Solo PDF)
      if (file.type !== 'application/pdf') {
        this.notificationService.showError('Formato Inválido', 'El acta debe ser estrictamente un documento PDF.');
        this.resetFileInput(inputElement);
        return;
      }

      // 2. Validar Peso (Máx 5MB)
      const maxSizeInBytes = 5 * 1024 * 1024; // 5 MB
      if (file.size > maxSizeInBytes) {
        this.notificationService.showError('Archivo muy pesado', 'El tamaño del documento no debe superar los 5 MB.');
        this.resetFileInput(inputElement);
        return;
      }

      // Todo en orden
      this.selectedFile.set(file);
    }
  }

  private resetFileInput(inputElement: HTMLInputElement): void {
    inputElement.value = '';
    this.selectedFile.set(null);
  }

  /**
   * Envía el FormData al backend
   */
  onSubmit(): void {
    if (!this.selectedFile()) {
      this.notificationService.showWarning('Atención', 'Debe adjuntar el acta de aprobación en formato PDF.');
      return;
    }

    this.isSubmitting.set(true);

    // Subimos el archivo
    this.phaseService.uploadFunctionalApproval(
      this.requirementId(),
      this.roleId(),
      this.selectedFile() as File
    ).subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.notificationService.toastSuccess('Aprobación Funcional registrada con éxito.');
        this.phaseService.refreshDashboard$.next(); 
        this.closeModal.emit();
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        const errorMessage = err.error?.message || 'Ocurrió un error al procesar el archivo.';
        this.notificationService.showError('Error de Carga', errorMessage);
        console.error('Error subiendo acta:', err.message);
      }
    });
  }
}