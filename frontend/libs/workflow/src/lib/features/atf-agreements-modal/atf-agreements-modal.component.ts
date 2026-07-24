import { Component, inject, input, output, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { WorkflowApiService } from '../../data-access/services/atf.service';
import { AtfAgreementPayload, AtfAgreementResponse, AtfAgreementDetail } from '../../data-access/models/atf-agreement.model';
import { HttpErrorResponse } from '@angular/common/http';
import { NotificationService } from '../../data-access/services/notitication.services';

@Component({
  selector: 'lib-atf-agreements-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './atf-agreements-modal.component.html',
  styleUrls: ['./atf-agreements-modal.component.scss']
})
export class AtfAgreementsModalComponent implements OnInit {
  // Inyección de dependencias
  private fb = inject(FormBuilder);
  private workflowApi = inject(WorkflowApiService);

  // Entradas y Salidas (Signals API)
  public requirementId = input.required<string>();
  public requirementCreationDate = input.required<string>();
  public initialMode = input<'create' | 'view'>('create'); 
  // La data del acuerdo (si estamos en modo view)
  public agreementData = input<AtfAgreementDetail | null>(null);
  // Regla de negocio: ¿La fase ATF está abierta?
  public isAtfOpen = input<boolean>(true);
  public closeModal = output<void>();
  public agreementSaved = output<void>();

  // Estado local reactivo
  public isSubmitting = signal<boolean>(false);
  public currentMode = signal<'create' | 'view' | 'edit'>('create');

  // Formulario Reactivo estrictamente tipado (nonNullable)
  public agreementForm = this.fb.nonNullable.group({
    agreement_date: ['', [Validators.required]],
    description: ['', [Validators.required, Validators.minLength(10)]]
  });

  private notificationService = inject(NotificationService);

  public maxDateAllowed = '';

  ngOnInit(): void {
    const tzOffset = (new Date()).getTimezoneOffset() * 60000;
    this.maxDateAllowed = (new Date(Date.now() - tzOffset)).toISOString().split('T')[0];
    // Inicializamos el modo actual basado en el input
    this.currentMode.set(this.initialMode());

    // Si viene un acuerdo, poblamos el formulario
    const data = this.agreementData();
    if (data && this.currentMode() !== 'create') {
      this.agreementForm.patchValue({
        agreement_date: data.agreement_date, // Asegúrate de que el formato coincida con YYYY-MM-DD
        description: data.description
      });
    }

    // Si es modo lectura ('view'), bloqueamos los inputs
    if (this.currentMode() === 'view') {
      this.agreementForm.disable();
    }
  }

  /**
   * Transición de Lectura a Edición
   */
  public switchToEdit(): void {
    this.currentMode.set('edit');
    this.agreementForm.enable();
  }


  /**
   * Maneja la acción de envío del formulario.
   */
  public onSubmit(): void {
    // 1. Failsafe de validación
    if (this.agreementForm.invalid) {
      this.agreementForm.markAllAsTouched();
      return;
    }

    if (this.isSubmitting()) {
      return;
    }

    this.isSubmitting.set(true);
    
    // Construcción del payload
    const payload: AtfAgreementPayload = {
      agreement_date: this.agreementForm.getRawValue().agreement_date,
      description: this.agreementForm.getRawValue().description
    };
    
    // 2. ORQUESTACIÓN INTELIGENTE (POST vs PUT)
    let request$; // Aquí guardaremos la petición a ejecutar

    if (this.currentMode() === 'edit') {
      // MODO EDICIÓN: Validación segura en lugar de usar "!"
      const currentAgreement = this.agreementData();
      
      if (!currentAgreement) {
        console.error('Error Crítico: Se intentó editar pero no hay datos del acuerdo en memoria.');
        this.isSubmitting.set(false);
        return; // Abortamos la ejecución por seguridad
      }
      
      request$ = this.workflowApi.putAgreement(this.requirementId(), currentAgreement.id, payload);
      
    } else {
      // MODO CREACIÓN: Usamos POST habitual
      request$ = this.workflowApi.postAgreement(this.requirementId(), payload);
    }

    // 3. Petición HTTP Unificada
    request$.subscribe({
      next: (response: AtfAgreementResponse) => {
        console.log('Respuesta del servidor:', response.message);
        
        this.isSubmitting.set(false);
        this.agreementForm.reset();
        
        // Títulos dinámicos según la acción
        const successTitle = this.currentMode() === 'edit' ? '¡Acuerdo Actualizado!' : '¡Acuerdo Registrado!';
        
        // Disparamos notificación dexito
        this.notificationService.showSuccess(successTitle, response.message ?? 'La operación se realizó con éxito.');
      

        
      },
      error: (error: HttpErrorResponse) => {
        console.error('Error al guardar el acuerdo ATF:', error);
        this.isSubmitting.set(false);

        const errorMsg = error.error?.message || 'Ocurrió un problema al intentar procesar el acuerdo. Verifique su conexión.';

        // Swal.fire({
        //   title: 'Error de Procesamiento',
        //   text: errorMsg,
        //   icon: 'error',
        //   confirmButtonColor: '#d33'
        // });
        this.notificationService.showError('Error de Procesamiento', errorMsg);
      }
    });
  }

  
}