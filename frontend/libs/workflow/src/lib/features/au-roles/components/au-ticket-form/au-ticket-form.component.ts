import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormArray, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { AuApiService } from '../../../../data-access/services/au-api.service';
import { AuRole } from '../../../../data-access/models/au-workflow.model';
import { NotificationService } from '../../../../data-access/services/notification.services'; 

@Component({
  selector: 'lib-au-ticket-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './au-ticket-form.component.html',
  styleUrls: ['./au-ticket-form.component.scss']
  
})
export class AuTicketFormComponent implements OnInit {
  
  // Inputs desde el Orquestador (au-roles.component)
  public requirementId = input.required<string>();
  public selectedRoles = input.required<AuRole[]>(); // Solo los roles con PENDING_AU marcados

  // Outputs para la Modal
  public closeForm = output<void>();
  public ticketCreated = output<void>(); // Avisa al padre para que recargue el workflow
  

  // Inyección de Dependencias
  private fb = inject(FormBuilder);
  private auApiService = inject(AuApiService);
  private notificationService = inject(NotificationService);

  // Estado Reactivo
  public ticketForm!: FormGroup;
  public isSubmitting = signal<boolean>(false);

  // Control Binario (Los archivos reales que irán al FormData)
  public generalFile = signal<File | null>(null);
  public planillasMap = new Map<string, File>();

  ngOnInit(): void {
    this.initForm();
  }

  /**
   * Inicializa el FormGroup asegurando validación estricta
   */
  private initForm(): void {
    this.ticketForm = this.fb.group({
      ticket_number: ['', [Validators.required, Validators.maxLength(50)]],
      request_date: ['', Validators.required],
      file: [null, Validators.required], // Validará el PDF general
      planillas: this.fb.array([])       // Validará los PDFs individuales
    });

    // Inyecta un control obligatorio por cada rol seleccionado
    const planillasArray = this.ticketForm.get('planillas') as FormArray;
    this.selectedRoles().forEach(() => {
      planillasArray.push(new FormControl(null, Validators.required));
    });
  }

  // Getter auxiliar para el HTML
  get planillasFormArray(): FormArray {
    return this.ticketForm.get('planillas') as FormArray;
  }

  // ==========================================
  // MANEJADORES DE ARCHIVOS (Validación Mime-Type Client-Side)
  // ==========================================

  public onGeneralFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      
      if (file.type !== 'application/pdf') {
        this.notificationService.showError('Formato Inválido', 'El soporte general debe ser un documento PDF.');
        this.ticketForm.get('file')?.setValue(null);
        this.generalFile.set(null);
        return;
      }
      
      this.generalFile.set(file);
      // Asigna el nombre al control solo para satisfacer el Validators.required
      this.ticketForm.get('file')?.setValue(file.name); 
    }
  }

  public onPlanillaFileChange(event: Event, roleId: string, index: number): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      
      if (file.type !== 'application/pdf') {
        this.notificationService.showError('Formato Inválido', 'La planilla individual debe ser un PDF.');
        this.planillasFormArray.at(index).setValue(null);
        this.planillasMap.delete(roleId);
        return;
      }
      
      this.planillasMap.set(roleId, file);
      this.planillasFormArray.at(index).setValue(file.name);
    }
  }

  // ==========================================
  // SUBMIT Y ENSAMBLAJE DE FORMDATA
  // ==========================================

  public submit(): void {
    if (this.ticketForm.invalid) {
      this.ticketForm.markAllAsTouched();
      this.notificationService.showWarning('Datos Incompletos', 'Verifique los campos requeridos y asegúrese de adjuntar todos los PDFs correspondientes.');
      return;
    }

    this.isSubmitting.set(true);
    const formData = new FormData();

    // 1. Datos escalares
    formData.append('ticket_number', this.ticketForm.value.ticket_number);
    formData.append('request_date', this.ticketForm.value.request_date);

    // 2. PDF General
    const gFile = this.generalFile();
    if (gFile) {
      formData.append('file', gFile);
    }

    // 3. Arreglo de Roles y Planillas Individuales
    this.selectedRoles().forEach(role => {
      // Inyecta el ID del rol para la regla 'role_ids' del backend
      formData.append('role_ids[]', role.id);
      
      // Inyecta el archivo indexado por el ID del rol
      const planilla = this.planillasMap.get(role.id);
      if (planilla) {
        formData.append(`planillas[${role.id}]`, planilla);
      }
    });

    // 4. Disparo al Backend
    this.auApiService.storeTicket(this.requirementId(), formData).subscribe({
      next: () => {
        this.isSubmitting.set(false);
        this.notificationService.toastSuccess('Ticket de Asignación a Usuario creado exitosamente.');
        this.auApiService.refreshDashboard$.next();
        this.ticketCreated.emit(); 
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        const errorMessage = err.error?.message || 'Ocurrio un error al procesar el archivo.';
        this.notificationService.showError('Error de Carga', errorMessage);
        console.error('Error subiendo acta:', err.message);
      }
    });
  }
}