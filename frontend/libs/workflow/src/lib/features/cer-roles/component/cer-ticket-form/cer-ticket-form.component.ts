import { Component, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { CerApiService } from '../../../../data-access/services/cer-api.service';
import { CerRole } from '../../../../data-access/models/cer-workflow.model';


@Component({
  selector: 'lib-cer-ticket-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './cer-ticket-form.component.html',
  styleUrls: ['./cer-ticket-form.component.scss']
})
export class CerTicketFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly cerApiService = inject(CerApiService);


  // Entradas de datos desde el componente padre (Orquestador)
  public requirementId = input.required<string>();
  public selectedRoleIds = input.required<string[]>();
  public selectedRoles = input.required<CerRole[]>(); 
  
  // Salidas para notificar al padre sobre el ciclo de vida del modal
  public closeForm = output<void>();
  public ticketCreated = output<void>();

  // Signals para el manejo del estado interno del formulario
  public isSubmitting = signal<boolean>(false);
  public selectedFile = signal<File | null>(null);

  // RN-CER: El número de ticket es provisto por CSAL, por lo tanto es requerido.
  public ticketForm: FormGroup = this.fb.group({
    ticket_number: ['', [Validators.required, Validators.maxLength(50)]],
    request_date: ['', Validators.required]
  });

  /**
   * Intercepta la selección del archivo, validando extensión y peso (Máx 5MB)
   */
  public onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      if (file.type === 'application/pdf' && file.size <= 5242880) {
        this.selectedFile.set(file);
      } else {
        this.selectedFile.set(null);
        alert('Por favor, seleccione un documento PDF válido que no exceda los 5MB.');
        input.value = ''; // Limpiamos el input si el archivo es inválido
      }
    }
  }

  /**
   * Consolida el FormData y delega la transacción al servicio de infraestructura
   */
  public submitTicket(): void {
    if (this.ticketForm.invalid || !this.selectedFile() || this.selectedRoleIds().length === 0) {
      this.ticketForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);

    const formData = new FormData();
    formData.append('ticket_number', this.ticketForm.get('ticket_number')?.value);
    formData.append('request_date', this.ticketForm.get('request_date')?.value);
    formData.append('file', this.selectedFile() as Blob);
    
    // Inyectamos el arreglo de UUIDs iterando sobre las selecciones del usuario
    this.selectedRoles().forEach((role, index) => {
      formData.append(`role_ids[${index}]`, role.id);
    });

    this.cerApiService.storeTicket(this.requirementId(), formData).subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.ticketCreated.emit(); // Notificamos éxito al padre para que actualice el Store
        this.closeModal();
      },
      error: (err) => {
        this.isSubmitting.set(false);
        console.error('Fallo en la transacción de ticket:', err);
      }
    });
  }

  public closeModal(): void {
    this.closeForm.emit();
  }
}