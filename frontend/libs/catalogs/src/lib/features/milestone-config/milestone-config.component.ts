// frontend/libs/catalogs/src/lib/features/milestone-config/milestone-config.component.ts

import { Component, OnInit, inject, signal, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MilestoneService } from '../../data-access/services/milestone.service';
import { Milestone } from '../../data-access/models/milestone.model';
import { NotificationService } from '@ecosgrti/workflow';


@Component({
  selector: 'lib-milestone-config',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './milestone-config.component.html',
  styleUrls: ['./milestone-config.component.scss']
})
export class MilestoneConfigComponent implements OnInit {
  private readonly milestoneService = inject(MilestoneService);
  private readonly fb = inject(FormBuilder);
  private readonly notificationService = inject(NotificationService);

  // Evento para notificar al Dashboard que debe cerrar este componente
  @Output() closeModal = new EventEmitter<void>();

  public milestones = signal<Milestone[]>([]);
  public selectedType = signal<string>('ROLES');
  public isEditing = signal<boolean>(false);
  public currentId = signal<string | null>(null);
  
  // Signal para controlar el modal interno del formulario reactivamente
  public showFormModal = signal<boolean>(false);

  public milestoneForm: FormGroup = this.fb.group({
    phase: ['', [Validators.required, Validators.maxLength(255)]],
    phase_code: ['', [Validators.required, Validators.maxLength(50)]],
    name: ['', [Validators.required, Validators.maxLength(255)]],
    status_code: ['', [Validators.required, Validators.maxLength(50)]],
    default_weight: [0, [Validators.required, Validators.min(0)]],
    management_type: ['ROLES', [Validators.required]],
    sort_order: [1, [Validators.required, Validators.min(1)]]
  });

  ngOnInit(): void {
    this.loadMilestones();
  }

  // Cierra el módulo completo y notifica al Dashboard
  public closeComponent(): void {
    this.closeModal.emit();
  }

  public loadMilestones(): void {
    this.milestoneService.getMilestones(this.selectedType()).subscribe({
      next: (data) => this.milestones.set(data),
      error: (err) => this.notificationService.showError('Error', err.error?.message || 'No se pudo cargar el catálogo maestro de hitos.')
    });
  }

  public onTypeChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.selectedType.set(value);
    this.loadMilestones();
  }

  public openCreateModal(): void {
    this.isEditing.set(false);
    this.currentId.set(null);
    this.milestoneForm.reset({ management_type: this.selectedType(), default_weight: 0 });
    this.showFormModal.set(true); // Abre el modal interno
  }

  public openEditModal(milestone: Milestone): void {
    this.isEditing.set(true);
    this.currentId.set(milestone.id || null);
    this.milestoneForm.patchValue(milestone);
    this.showFormModal.set(true); // Abre el modal interno
  }

  public closeFormModal(): void {
    this.showFormModal.set(false); // Cierra el modal interno
  }

  public saveMilestone(): void {
    if (this.milestoneForm.invalid) {
      this.milestoneForm.markAllAsTouched();
      return;
    }

    const payload: Milestone = this.milestoneForm.value;
    const id = this.currentId();

    if (this.isEditing() && id) {
      this.milestoneService.updateMilestone(id, payload).subscribe({
        next: (res) => {
          this.notificationService.toastSuccess(res.message || 'Operación exitosa');
          this.closeFormModal();
          this.loadMilestones();
        },
        error: (err) => this.notificationService.showError('Error', err.error?.message || 'Error al actualizar.')
        
      });
    } else {
      this.milestoneService.createMilestone(payload).subscribe({
        next: (res) => {
          //Swal.fire('Éxito', res.message, 'success');
          this.notificationService.toastSuccess(res.message || 'Operación exitosa');
          this.closeFormModal();
          this.loadMilestones();
        },
        error: (err) => this.notificationService.showError('Error', err.error?.message || 'Error al registrar.')
      });
    }
  }

  public deleteMilestone(id?: string): void {
    if (!id) return;

    this.notificationService.confirm('Confirmación', '<span class="fw-bold mb-2">¿Estás seguro que deseas eliminar este hito?</span><br><br><span class="text-muted mt-2">Nota: Se validara la integridad referencial.</span>').then((confirmed) => {
      if (confirmed) {
        this.milestoneService.deleteMilestone(id).subscribe({
          next: (res) => {
            this.notificationService.toastSuccess(res.message || 'Hito eliminado exitosamente');
            //Swal.fire('Eliminado', res.message, 'success');
            this.loadMilestones();
          },
          error: (err) => this.notificationService.showError('Error', err.error?.message || 'No se pudo eliminar el hito.')
        });
      }
    });
  }


    // Swal.fire({
    //   title: '¿Estás seguro?',
    //   text: 'Se validará la integridad referencial antes de eliminar.',
    //   icon: 'warning',
    //   showCancelButton: true,
    //   confirmButtonColor: '#0d6efd',
    //   cancelButtonColor: '#6c757d',
    //   confirmButtonText: 'Sí, eliminar',
    //   cancelButtonText: 'Cancelar'
    // }).then((result) => {
    //   if (result.isConfirmed) {
    //     this.milestoneService.deleteMilestone(id).subscribe({
    //       next: (res) => {
    //         Swal.fire('Eliminado', res.message, 'success');
    //         this.loadMilestones();
    //       },
    //       error: (err) => this.notificationService.showError('Error', err.error?.message || 'No se pudo eliminar.', 'error')
    //     });
    //   }
    // });
 // }
}