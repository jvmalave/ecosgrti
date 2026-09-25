import { Component, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { PapApiService } from '../../../../data-access/services/pap-api.service';
import { PapRole, PapOrderResponse } from '../../../../data-access/models/pap-workflow.model';
import { HttpErrorResponse } from '@angular/common/http';
//import { NotificationService } from '../../../../data-access/services/notification.services';


@Component({
  selector: 'lib-pap-order-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './pap-order-form.component.html',
  styleUrls: ['./pap-order-form.component.scss'] // Hereda los estilos de tu modal corporativo
})
export class PapOrderFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly papApiService = inject(PapApiService);

  // Entradas de datos desde el componente padre (PAP Roles)
  public requirementId = input.required<string>();
  public selectedRoleIds = input.required<string[]>();
  public selectedRoles = input.required<PapRole[]>(); 
  
  // Salidas para notificar al padre sobre el ciclo de vida del modal
  public closeForm = output<void>();
  public orderCreated = output<PapOrderResponse>()

  // Signals para el manejo del estado interno del formulario
  public isSubmitting = signal<boolean>(false);
  public selectedFile = signal<File | null>(null);

  // RN-PAP: El número de Orden es emitido por CSPE, y es estrictamente requerido.
  public orderForm: FormGroup = this.fb.group({
    order_number: ['', [Validators.required, Validators.maxLength(50)]],
    date: ['', Validators.required]
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
  public submitOrder(): void {
    if (this.orderForm.invalid || !this.selectedFile() || this.selectedRoleIds().length === 0) {
      this.orderForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);

    const formData = new FormData();
    formData.append('order_number', this.orderForm.get('order_number')?.value);
    formData.append('date', this.orderForm.get('date')?.value);
    formData.append('file', this.selectedFile() as Blob);
    
    // Inyectamos el arreglo de UUIDs iterando sobre las selecciones del usuario
    this.selectedRoles().forEach((role, index) => {
      formData.append(`role_ids[${index}]`, role.id);
    });

    // 👁️‍🗨️ Asegúrate de crear este método en pap-api.service.ts apuntando a POST /workflow/pap/orders
    this.papApiService.storeOrder(this.requirementId(), formData).subscribe({
      next: (response) => {
    this.isSubmitting.set(false);
    this.orderCreated.emit(response); 
    this.closeModal();
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        console.error('Fallo en la transacción de orden de transporte:', err.message);
      }
    });
  }

  public closeModal(): void {
    this.closeForm.emit();
  }
}