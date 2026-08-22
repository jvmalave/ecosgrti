import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { CeeApiService } from '../../../../data-access/services/cee-api.service';
import { CeeDeliverable } from '../../../../data-access/models/cee-workflow.model';

@Component({
  selector: 'lib-cee-result-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './cee-result-form.component.html',
  styleUrls: ['./cee-result-form.component.scss']
})
export class CeeResultFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly ceeApiService = inject(CeeApiService);

  // ==========================================
  // INPUTS & OUTPUTS
  // ==========================================
  public ticketId = input.required<string>();
  public ticketNumber = input.required<string>();
  public deliverablesInTicket = input.required<CeeDeliverable[]>();
  public rrti = input.required<string>();
  
  // Input para controlar si estamos en modo lectura
  public viewMode = input<boolean>(false);
  
  public closeForm = output<void>();
  public resultRegistered = output<void>();

  // ==========================================
  // ESTADOS LOCALES
  // ==========================================
  public isSubmitting = signal<boolean>(false);
  public selectedFile = signal<File | null>(null);
  public resultForm!: FormGroup;

  ngOnInit(): void {
    // 1. Inicializamos el FormGroup vacío
    this.resultForm = this.fb.group({});

    // Agrega los controles dinámicamente por cada entregable
    this.deliverablesInTicket().forEach(deliverable => {
      this.resultForm.addControl(
        `verdict_${deliverable.id}`, 
        this.fb.control<string | null>(null, Validators.required)
      );
      this.resultForm.addControl(
        `reason_${deliverable.id}`, 
        this.fb.control<string | null>({ value: null, disabled: true })
      );
    });

    // EVALUACIÓN DEL MODO (LECTURA VS CREACIÓN)
    if (this.viewMode()) {
      // MODO LECTURA: Precargamos la data histórica
      this.deliverablesInTicket().forEach(deliverable => {
        const isApproved = deliverable.status === 'CERTIFIED'; 
        this.resultForm.get(`verdict_${deliverable.id}`)?.setValue(isApproved ? 'APPROVED' : 'REJECTED');
        
        if (!isApproved && deliverable.rejection_reason) {
          this.resultForm.get(`reason_${deliverable.id}`)?.setValue(deliverable.rejection_reason);
        }
      });
      
      // Bloqueamos el formulario completo para evitar mutaciones
      this.resultForm.disable(); 
      
    } else {
      // MODO CREACIÓN: Reactividad para habilitar/deshabilitar el motivo
      this.deliverablesInTicket().forEach(deliverable => {
        this.resultForm.get(`verdict_${deliverable.id}`)?.valueChanges.subscribe(verdict => {
          const reasonControl = this.resultForm.get(`reason_${deliverable.id}`);
          
          if (verdict === 'REJECTED') {
            reasonControl?.enable();
            reasonControl?.setValidators([Validators.required]);
          } else {
            reasonControl?.disable();
            reasonControl?.clearValidators();
            reasonControl?.setValue(null); 
          }
          
          reasonControl?.updateValueAndValidity();
        });
      });
    }
  }

  // ==========================================
  // GESTIÓN DE ARCHIVOS Y ENVÍO
  // ==========================================
  public onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      if (file.type === 'application/pdf' && file.size <= 5242880) {
        this.selectedFile.set(file);
      } else {
        this.selectedFile.set(null);
        alert('Seleccione un documento PDF válido que no exceda los 5MB.');
        input.value = ''; 
      }
    }
  }

  public submitResult(): void {
    // 1. Validamos que haya subido el PDF
    if (!this.selectedFile()) {
      alert('⚠️ No se puede registrar: Debe adjuntar el Acta de Aprobación/Rechazo (PDF).');
      return;
    }

    // 2. Validamos que todos los entregables tengan un veredicto marcado
    if (this.resultForm.invalid) {
      alert('⚠️ Formulario incompleto: Debe emitir un veredicto (Aprobado o Rechazado) para TODOS los entregables y justificar los rechazos.');
      this.resultForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);

    const evaluationsPayload = this.deliverablesInTicket().map(deliverable => {
      const veredicto = this.resultForm.get(`verdict_${deliverable.id}`)?.value;
      return {
        id: deliverable.id,
        is_approved: veredicto === 'APPROVED', 
        rejection_reason: this.resultForm.get(`reason_${deliverable.id}`)?.value || null
      };
    });

    const formData = new FormData();
    formData.append('file', this.selectedFile() as Blob);
    formData.append('evaluations', JSON.stringify(evaluationsPayload));

    this.ceeApiService.registerResult(this.ticketId(), formData).subscribe({
      next: (response) => {
        this.isSubmitting.set(false);
        this.resultRegistered.emit(); // Cierra el modal y actualiza tablas
        this.closeModal();
        console.log('Dictamen registrado:', response);
      },
      error: (err) => {
        console.error('Error al registrar el dictamen:', err);
        alert('Hubo un error de comunicación con el servidor al registrar el dictamen.');
        this.isSubmitting.set(false);
      }
    });
  }

  // Función para descargar/ver el PDF en modo lectura
  public viewRequestDocument(): void {
    this.ceeApiService.downloadRequestFile(this.ticketId()).subscribe({
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

  // Función para ver el Acta de Dictamen (Solo en modo lectura)
  public viewResultDocument(): void {
    this.ceeApiService.downloadResultFile(this.ticketId()).subscribe({
      next: (blob: Blob) => {
        const fileURL = URL.createObjectURL(blob);
        window.open(fileURL, '_blank');
        setTimeout(() => URL.revokeObjectURL(fileURL), 10000);
      },
      error: (err) => {
        console.error('Error visualizando el dictamen:', err);
        alert('No se pudo cargar el acta de dictamen.');
      }
    });
  }

  public closeModal(): void {
    this.closeForm.emit();
  }
}