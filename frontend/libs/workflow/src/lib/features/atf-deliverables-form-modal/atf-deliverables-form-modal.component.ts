import { Component, EventEmitter, inject, input, OnInit, Output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Deliverable } from '../../data-access/models/deliverable.model'; // Asegúrate de tener esta interfaz
import { DeliverableService } from '../../data-access/services/deliverable.service';
import { NotificationService } from '../../data-access/services/notification.services';

@Component({
  selector: 'lib-atf-deliverable-form-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './atf-deliverables-form-modal.component.html',
  styleUrls: ['./atf-deliverables-form-modal.component.scss']
})
export class DeliverableFormModalComponent implements OnInit {

  public requirementId = input.required<string>();
  public deliverableToEdit = input<Deliverable | null>(null);
  public isPhaseClosed = input<boolean>(false);

  @Output() closeModal = new EventEmitter<void>();
  @Output() deliverableSaved = new EventEmitter<void>();

  private fb = inject(FormBuilder);
  private deliverableService = inject(DeliverableService);
  private notificationService = inject(NotificationService);

  public isSubmitting = signal<boolean>(false);
  public isEditMode = signal<boolean>(false);
  public isViewMode = signal<boolean>(false);

  public deliverableForm!: FormGroup;

  ngOnInit(): void {
    this.initForm();
    this.checkMode();
  }

  private initForm(): void {
    this.deliverableForm = this.fb.group({
      name: ['', [Validators.required, Validators.maxLength(255)]],
      description: ['', [Validators.required, Validators.maxLength(500)]]
    });
  }

  private checkMode(): void {
    const item = this.deliverableToEdit();
    if (item) {
      this.isEditMode.set(true);
      this.isViewMode.set(true);
      this.deliverableForm.patchValue({
        name: item.name,
        description: item.description
      });
      this.deliverableForm.disable();
    }
  }

  public enableEdit(): void {
    this.isViewMode.set(false);
    this.deliverableForm.enable();
  }

  public onSubmit(): void {
    if (this.deliverableForm.invalid) {
      this.deliverableForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    const data = this.deliverableForm.value;
    const item = this.deliverableToEdit();

    const request$ = item 
      ? this.deliverableService.updateDeliverable(item.id, data)
      : this.deliverableService.createDeliverable(this.requirementId(), data);

    request$.subscribe({
      next: (response) => {
        this.notificationService.toastSuccess(item ? 'Entregable actualizado' : 'Entregable registrado');
        this.isSubmitting.set(false);
        this.deliverableSaved.emit();
        this.closeModal.emit();
        console.log(response)
      },
      error: (err) => {
        console.error('Error en operación de entregable', err);
        this.isSubmitting.set(false);
        this.notificationService.showError('Error al procesar la solicitud', 'Vueve a intentarlo');
        console.log(err)
      }
    });
  }
}