import { Component, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { CeeApiService } from '../../../../data-access/services/cee-api.service';
import { CeeDeliverable } from '../../../../data-access/models/cee-workflow.model';

@Component({
  selector: 'lib-cee-ticket-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './cee-ticket-form.component.html',
  styleUrls: ['./cee-ticket-form.component.scss']
})
export class CeeTicketFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly ceeApiService = inject(CeeApiService);

  public requirementId = input.required<string>();
  public selectedDeliverableIds = input.required<string[]>();
  public selectedDeliverables = input.required<CeeDeliverable[]>(); 
  
  public closeForm = output<void>();
  public ticketCreated = output<void>();

  public isSubmitting = signal<boolean>(false);
  public selectedFile = signal<File | null>(null);

  // 🟢 RN-CEE: Removido el ticket_number, será autogenerado por Laravel
  public ticketForm: FormGroup = this.fb.group({
    request_date: ['', Validators.required]
  });

  public onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      if (file.type === 'application/pdf' && file.size <= 5242880) {
        this.selectedFile.set(file);
      } else {
        this.selectedFile.set(null);
        alert('Por favor, seleccione un documento PDF válido que no exceda los 5MB.');
        input.value = ''; 
      }
    }
  }

  public submitTicket(): void {
    if (this.ticketForm.invalid || !this.selectedFile() || this.selectedDeliverableIds().length === 0) {
      this.ticketForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);

    const formData = new FormData();
    // Ya no enviamos el ticket_number
    formData.append('request_date', this.ticketForm.get('request_date')?.value);
    formData.append('file', this.selectedFile() as Blob);
    
    this.selectedDeliverables().forEach((deliverable, index) => {
      formData.append(`deliverable_ids[${index}]`, deliverable.id);
    });

    this.ceeApiService.storeTicket(this.requirementId(), formData).subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.ticketCreated.emit(); 
        this.closeModal();
      },
      error: (err) => {
        this.isSubmitting.set(false);
        console.error('Fallo en la transacción de solicitud:', err);
      }
    });
  }

  public closeModal(): void {
    this.closeForm.emit();
  }
}