import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { CerApiService } from '../../../../data-access/services/cer-api.service';
import { CerRole } from '../../../../data-access/models/cer-workflow.model';

@Component({
  selector: 'lib-cer-result-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './cer-result-form.component.html',
  styleUrls: ['./cer-result-form.component.scss']
})
export class CerResultFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly cerApiService = inject(CerApiService);

  // ==========================================
  // INPUTS & OUTPUTS
  // ==========================================
  public ticketId = input.required<string>();
  public ticketNumber = input.required<string>();
  public rolesInTicket = input.required<CerRole[]>();
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

    // Agrega los controles dinámicamente
    this.rolesInTicket().forEach(role => {
      this.resultForm.addControl(
        `verdict_${role.id}`, 
        this.fb.control<string | null>(null, Validators.required)
      );
      this.resultForm.addControl(
        `reason_${role.id}`, 
        this.fb.control<string | null>({ value: null, disabled: true })
      );
    });

    // EVALUACIÓN DEL MODO (LECTURA VS CREACIÓN)
    if (this.viewMode()) {
      // MODO LECTURA: Precargamos la data histórica
      this.rolesInTicket().forEach(role => {
        const isApproved = role.status === 'CERTIFIED'; 
        this.resultForm.get(`verdict_${role.id}`)?.setValue(isApproved ? 'APPROVED' : 'REJECTED');
        
        if (!isApproved && role.rejection_reason) {
          this.resultForm.get(`reason_${role.id}`)?.setValue(role.rejection_reason);
        }
      });
      
      // Bloqueamos el formulario completo para evitar mutaciones
      this.resultForm.disable(); 
      
    } else {
      // MODO CREACIÓN: Reactividad para habilitar/deshabilitar el motivo
      this.rolesInTicket().forEach(role => {
        this.resultForm.get(`verdict_${role.id}`)?.valueChanges.subscribe(verdict => {
          const reasonControl = this.resultForm.get(`reason_${role.id}`);
          
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
    if (this.resultForm.invalid || !this.selectedFile()) {
      this.resultForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);

    const evaluationsPayload = this.rolesInTicket().map(role => {
      const veredicto = this.resultForm.get(`verdict_${role.id}`)?.value;
      return {
        id: role.id,
        is_approved: veredicto === 'APPROVED', 
        rejection_reason: this.resultForm.get(`reason_${role.id}`)?.value || null
      };
    });

    const formData = new FormData();
    formData.append('file', this.selectedFile() as Blob);
    formData.append('evaluations', JSON.stringify(evaluationsPayload));

    this.cerApiService.registerResult(this.ticketId(), formData).subscribe({
      next: (response) => {
        this.isSubmitting.set(false);
        this.resultRegistered.emit();
        this.closeModal();
        console.log('Dictamen registrado:', response);
      },
      error: (err) => {
        console.error('Error al registrar el dictamen:', err);
        this.isSubmitting.set(false);
      }
    });
  }

  // Función para descargar/ver el PDF en modo lectura
  public viewPdfDocument(): void {
    this.cerApiService.downloadTicketFile(this.ticketId()).subscribe({
      next: (blob: Blob) => {
        const fileURL = URL.createObjectURL(blob);
        window.open(fileURL, '_blank');
        setTimeout(() => URL.revokeObjectURL(fileURL), 10000);
      },
      error: (err) => {
        console.error('Error visualizando el PDF:', err);
      }
    });
  }

  public viewRequestDocument(): void {
    this.cerApiService.downloadRequestFile(this.ticketId()).subscribe({
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