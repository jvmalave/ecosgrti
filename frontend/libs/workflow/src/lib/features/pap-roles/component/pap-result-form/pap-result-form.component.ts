import { Component, OnInit, inject, input, output, signal} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, ValidatorFn } from '@angular/forms';
import { PapApiService } from '../../../../data-access/services/pap-api.service';
import { PapRole } from '../../../../data-access/models/pap-workflow.model';
import { HttpErrorResponse } from '@angular/common/http';

@Component({
  selector: 'lib-pap-result-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './pap-result-form.component.html',
  styleUrls: ['./pap-result-form.component.scss'] 
})
export class PapResultFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly papApiService = inject(PapApiService);


  // ==========================================
  // INPUTS (ENTRADAS)
  // ==========================================
  public orderId = input.required<string>();
  public orderNumber = input.required<string>();
  public rolesInOrder = input.required<PapRole[]>();
  public rrti = input.required<string>();
  public viewMode = input<boolean>(false); 
  

  // ==========================================
  // OUTPUTS (SALIDAS)
  // ==========================================
  public closeForm = output<void>();
  public resultRegistered = output<void>();
  
  
  // ==========================================
  // SIGNALS Y STATES
  //===========================================
  public isSubmitting = signal<boolean>(false);
  public selectedFile = signal<File | null>(null);
  public resultForm!: FormGroup;

  ngOnInit(): void {
    this.initForm();
  }

  private initForm(): void {
    const formControls: Record<string, (string | ValidatorFn)[]> = {};
    
    this.rolesInOrder().forEach(role => {
      // Por defecto asumimos que fue aprobado...
      let initialStatus = 'approved';
      let initialReason = '';

      if (this.viewMode()) {
        const historyItem = role.rejection_history?.find(h => 
          (h.order_number || '').trim().toUpperCase() === this.orderNumber().trim().toUpperCase()
        );

        // Si existe un registro de rechazo en esta orden, cambiamos el veredicto
        if (historyItem) {
          initialStatus = 'rejected';
          initialReason = historyItem.reason || '';
        }
      }

      formControls[`status_${role.id}`] = [initialStatus, Validators.required];
      formControls[`reason_${role.id}`] = [initialReason];
    });

    this.resultForm = this.fb.group(formControls);

    // Bloqueamos el formulario en modo lectura
    if (this.viewMode()) {
      this.resultForm.disable();
    }
  }

  public onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      if (file.type === 'application/pdf' && file.size <= 5242880) {
        this.selectedFile.set(file);
      } else {
        this.selectedFile.set(null);
        alert('Por favor, seleccione un acta PDF válida (Máx 5MB).');
        input.value = ''; 
      }
    }
  }

  public submitResult(): void {
    if (this.resultForm.invalid || !this.selectedFile()) {
      this.resultForm.markAllAsTouched();
      if (!this.selectedFile()) alert('El acta de despliegue (PDF) es obligatoria.');
      return;
    }

    this.isSubmitting.set(true);
    const formData = new FormData();
    formData.append('file', this.selectedFile() as Blob);
    
    // Construimos el arreglo de evaluaciones que espera el Trait polimórfico
    this.rolesInOrder().forEach((role, index) => {
      const isApproved = this.resultForm.get(`status_${role.id}`)?.value === 'approved';
      const reason = this.resultForm.get(`reason_${role.id}`)?.value;

      formData.append(`evaluations[${index}][id]`, role.id);
      formData.append(`evaluations[${index}][is_approved]`, isApproved ? '1' : '0');
      
      if (!isApproved && reason) {
        // La llave 'rejection_reason' es la que lee internamente el Trait por defecto antes de mapearla a 'fail_reason'
        formData.append(`evaluations[${index}][rejection_reason]`, reason); 
      }
    });

    this.papApiService.registerResult(this.orderId(), formData).subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.resultRegistered.emit();
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        console.error('Fallo registrando el dictamen:', err.message);
      }
    });
  }

  /**
   * Descarga y visualiza el PDF de la Orden de Transporte Base
   */
  public viewOrderDocument(): void {
    if (!this.orderId()) return;

    // Sustituye 'downloadOrderFile' por el nombre real de tu método en el servicio
    this.papApiService.downloadOrderFile(this.orderId()).subscribe({
      next: (blob: Blob) => {
        const fileURL = URL.createObjectURL(blob);
        window.open(fileURL, '_blank');
        setTimeout(() => URL.revokeObjectURL(fileURL), 10000);
      },
      error: (err) => {
        console.error('Error visualizando la Orden de Transporte:', err);
        alert('No se pudo cargar el documento de la orden.');
      }
    });
  }

  /**
   * Descarga y visualiza el Acta en PDF del Dictamen
   */
  public viewResultDocument(): void {
    if (!this.orderId()) return;

    // Sustituye 'downloadResultFile' por el nombre real de tu método en el servicio
    this.papApiService.downloadResultFile(this.orderId()).subscribe({
      next: (blob: Blob) => {
        const fileURL = URL.createObjectURL(blob);
        window.open(fileURL, '_blank');
        setTimeout(() => URL.revokeObjectURL(fileURL), 10000);
      },
      error: (err) => {
        console.error('Error visualizando la solicitud:', err);
        alert('No se pudo cargar el documento de solicitud.');
      }
    });
  }

  
  

  public closeModal(): void {
    this.closeForm.emit();
  }
}