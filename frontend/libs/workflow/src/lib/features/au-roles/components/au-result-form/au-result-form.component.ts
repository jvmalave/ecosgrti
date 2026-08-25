import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormArray, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { AuApiService } from '../../../../data-access/services/au-api.service';
import { NotificationService } from '../../../../data-access/services/notification.services';
import { AuRole, FormEvaluationData } from '../../../../data-access/models/au-workflow.model';


@Component({
  selector: 'lib-au-result-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './au-result-form.component.html',
  styleUrls: ['./au-result-form.component.scss']
})
export class AuResultFormComponent implements OnInit {
  
  public ticketId = input.required<string>();
  public ticketNumber = input.required<string>();
  public roles = input.required<AuRole[]>(); 
  public requirementId = input<string>('');
  public isViewMode = input<boolean>(false); 
  
  public closeModal = output<void>();
  public resultRegistered = output<void>();

  private fb = inject(FormBuilder);
  private auApiService = inject(AuApiService);
  private notificationService = inject(NotificationService);

  public resultForm!: FormGroup;
  public isSubmitting = signal<boolean>(false);
  public selectedFile = signal<File | null>(null);

  ngOnInit(): void {
    this.buildForm();

    if (this.isViewMode()) {
      this.resultForm.disable();
    }
  }

  private buildForm(): void {
    this.resultForm = this.fb.group({
      reception_date: ['', Validators.required],
      evaluations: this.fb.array([])
    });

    const evaluationsArray = this.resultForm.get('evaluations') as FormArray;

    this.roles().forEach(role => {
      const roleName = role.requirement_role?.role_name || role.role_name || 'Rol Desconocido';
      
      const roleGroup = this.fb.group({
        id: [role.id, Validators.required],
        role_name: [{ value: roleName, disabled: true }], 
        verdict: ['TOTAL', Validators.required], // 🟢 Botonera de 3 estados, iniciada en TOTAL
        rejection_reason: [''] 
      });

      // 🟢 Escuchador reactivo adaptado a los 3 estados
      roleGroup.get('verdict')?.valueChanges.subscribe(verdictValue => {
        const reasonCtrl = roleGroup.get('rejection_reason');
        if (verdictValue !== 'TOTAL') {
          reasonCtrl?.setValidators([Validators.required, Validators.minLength(10)]);
        } else {
          reasonCtrl?.clearValidators();
          reasonCtrl?.setValue('');
        }
        reasonCtrl?.updateValueAndValidity();
      });

      evaluationsArray.push(roleGroup);
    });
  }

  get evaluations(): FormArray {
    return this.resultForm.get('evaluations') as FormArray;
  }

  /**
   * Controla la botonera excluyente (Total / Parcial / Sin Asignar)
   */
  public setVerdict(index: number, verdictType: 'TOTAL' | 'PARCIAL' | 'NO_ASIGNADO'): void {
    const roleGroup = this.evaluations.at(index);
    roleGroup.get('verdict')?.setValue(verdictType);
  }

  public onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
      if (file.type !== 'application/pdf') {
        this.notificationService.showError('Formato Inválido', 'El soporte de CSAL debe ser estrictamente un archivo PDF.');
        input.value = ''; 
        this.selectedFile.set(null);
        return;
      }
      this.selectedFile.set(file);
    }
  }

  public submitForm(): void {
    if (this.resultForm.invalid || !this.selectedFile()) {
      this.resultForm.markAllAsTouched();
      this.notificationService.showError('Formulario Incompleto', 'Debe adjuntar el PDF probatorio y completar las justificaciones de rechazo.');
      return;
    }

    this.isSubmitting.set(true);
    const formValue = this.resultForm.getRawValue(); 
    const formData = new FormData();

    formData.append('reception_date', formValue.reception_date);
    formData.append('result_file', this.selectedFile() as File);

    // Mapeo inteligente para mantener la compatibilidad con el backend polimórfico
    formValue.evaluations.forEach((evalData: FormEvaluationData, index: number) => {
      formData.append(`evaluations[${index}][id]`, evalData.id);
      
      const isApproved = evalData.verdict === 'TOTAL' ? '1' : '0';
      formData.append(`evaluations[${index}][is_approved]`, isApproved);
      
      if (isApproved === '0') {
        // Inyectamos el dictamen específico en la justificación para no perder trazabilidad
        const detailedReason = `[Dictamen ${evalData.verdict}] ${evalData.rejection_reason}`;
        formData.append(`evaluations[${index}][rejection_reason]`, detailedReason);
      }
    });

    this.auApiService.registerResult(this.ticketId(), formData).subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.notificationService.showSuccess('Dictamen Registrado', `Los resultados del ticket ${this.ticketNumber()} han sido procesados.`);
        this.auApiService.refreshDashboard$.next();
        this.resultRegistered.emit(); 
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        this.notificationService.showError('Error Transaccional', 'No se pudo procesar el dictamen.');
        console.error('Fallo en registerResult:', err.message);
      }
    });
  }

  // ... (tus otros métodos)

  /**
   * 🟢 Motor privado para procesar la visualización del Blob PDF
   */
  private processDocumentView(path: string): void {
    // // Activamos un estado de carga opcional si tuvieras un spinner general, o simplemente notificamos
    // this.notificationService.showSuccess('Descargando', 'Obteniendo documento seguro del servidor...');

    this.auApiService.downloadDocument(path).subscribe({
      next: (blob: Blob) => {
        // Creamos una URL local en la memoria del navegador para el PDF
        const fileUrl = window.URL.createObjectURL(blob);
        
        // Abrimos el PDF en una nueva pestaña
        window.open(fileUrl, '_blank');
        
        // Nota: El navegador limpiará la URL cuando se cierre, pero puedes agregar 
        // temporizadores para revocarla con URL.revokeObjectURL(fileUrl) si lo deseas.
      },
      error: (err: HttpErrorResponse) => {
        console.error('Error al descargar el PDF:', err);
        this.notificationService.showError('Error de Archivo', 'No se pudo recuperar el documento del servidor.');
      }
    });
  }

  /**
   * Abre los documentos generales del Ticket o del Dictamen
   */
  public openPdf(type: 'ticket' | 'result'): void {
    const ticket = this.roles()[0]?.ticket;
    if (!ticket) return;

    const path = type === 'ticket' 
      ? ticket.file_path 
      : (ticket.result_file || ticket.result_file_path);
    
    if (path) {
      this.processDocumentView(path);
    } else {
      this.notificationService.showError('Error', 'El documento físico no se encuentra disponible.');
    }
  }

  /**
   * Abre el documento (planilla) individual de un rol específico
   */
  public openPlanillaPdf(index: number): void {
    const role = this.roles()[index];
    const path = role?.planilla_path;
    
    if (path) {
      this.processDocumentView(path);
    } else {
      this.notificationService.showError('Error', 'La planilla de este rol no se encuentra disponible.');
    }
  }

  

  
}