// import { Component, inject, input, signal, OnInit } from '@angular/core';
// import { CommonModule } from '@angular/common';
// import { FormBuilder, FormGroup, FormArray, ReactiveFormsModule, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
// import { Router } from '@angular/router';
// import { EstimationService } from '../../data-access/services/estimation';
// import { EstimationPayload, SavedEstimatedPhase } from '../../data-access/models/requirement.model';
// import { NotificationService } from '@app/workflow';
// import { switchMap } from 'rxjs/operators';

// @Component({
//   selector: 'lib-estimation-form',
//   standalone: true,
//   imports: [CommonModule, ReactiveFormsModule],
//   templateUrl: './estimation-form.component.html',
//   styleUrls: ['./estimation-form.component.scss']
// })
// export class EstimationFormComponent implements OnInit {

//   private fb = inject(FormBuilder);
//   private estimationService = inject(EstimationService);
//   private router = inject(Router);
//   private notificationService = inject(NotificationService); 

//   requirementId = input.required<string>();
//   justification = input.required<string>();

//   requirementRrti = signal<string>('Cargando...'); 
//   isLocked = signal<boolean>(false);               
//   isLoadingData = signal<boolean>(true);           

//   isSubmitting = signal<boolean>(false);
//   hasSavedData = signal<boolean>(false); 

//   private defaultPhases = ['ATF', 'DISENO', 'CONSTRUCCION', 'PRUEBAS', 'CERTIFICACION', 'IMPLEMENTACION'];

//   getPhaseLabel(phaseCode: string): string {
//     const labels: Record<string, string> = {
//       'ATF': 'ATF',
//       'DISENO': 'Diseño Técnico',
//       'CONSTRUCCION': 'Construcción',
//       'PRUEBAS': 'Pruebas Integrales',
//       'CERTIFICACION': 'Certificación',
//       'IMPLEMENTACION': 'Implementación'
//     };
//     return labels[phaseCode] || phaseCode;
//   }

//   estimationForm: FormGroup = this.fb.group({
//     phases: this.fb.array([])
//   }, { validators: this.chronologyValidator });

//   constructor() {
//     this.initializeForm();
//   }

//   ngOnInit(): void {
//     this.loadEstimationData();
//   }

//   get phases(): FormArray {
//     return this.estimationForm.get('phases') as FormArray;
//   }

//   private initializeForm(): void {
//     this.defaultPhases.forEach(phaseName => {
//       const phaseGroup = this.fb.group({
//         phase_name: [{ value: phaseName, disabled: true }], 
//         start_date: ['', Validators.required],
//         end_date: ['', Validators.required],
//         estimated_hours: ['', [Validators.required, Validators.min(1)]]
//       });
//       this.phases.push(phaseGroup);
//     });
//   }

//   private chronologyValidator(control: AbstractControl): ValidationErrors | null {
//     const phasesArray = control.get('phases') as FormArray;
//     if (!phasesArray) return null;

//     let previousStartDate: Date | null = null;

//     for (let i = 0; i < phasesArray.length; i++) {
//       const phaseGroup = phasesArray.at(i);
//       const startVal = phaseGroup.get('start_date')?.value;
//       const endVal = phaseGroup.get('end_date')?.value;
//       const phaseName = phaseGroup.get('phase_name')?.value || `Fase ${i + 1}`;

//       if (startVal && endVal) {
//         const currentStart = new Date(startVal);
//         const currentEnd = new Date(endVal);

//         if (currentEnd < currentStart) {
//           return { chronologyError: `Error en ${phaseName}: La fecha de fin no puede ser anterior a la fecha de inicio.` };
//         }

//         if (previousStartDate !== null && currentStart < previousStartDate) {
//           return { chronologyError: `Ruptura de secuencia: ${phaseName} no puede iniciar antes de la fecha de inicio de su predecesora.` };
//         }
//         previousStartDate = currentStart;
//       }
//     }
//     return null;
//   }
  
//   private loadEstimationData(): void {
//     this.isLoadingData.set(true);
    
//     this.estimationService.getEstimationDetails(this.requirementId()).subscribe({
//       next: (response) => {
//         const data = response.data;
//         this.requirementRrti.set(data.rrti);
//         this.isLocked.set(data.is_locked);

//         if (data.estimation && data.estimation.estimated_phases) {
//           this.patchFormWithSavedData(data.estimation.estimated_phases);
//           this.hasSavedData.set(true);
//         }

//         if (data.is_locked) {
//           this.estimationForm.disable(); 
//         }

//         this.isLoadingData.set(false);
//       },
//       error: (err) => {
//         // 🚀 3. Uso del servicio: Error de carga
//         this.notificationService.showError('Error', 'Error al cargar los datos del requerimiento.');
//         this.isLoadingData.set(false);
//         console.log('Error al cargar los datos del requerimiento:', err);
//       }
//     });
//   }

//   private patchFormWithSavedData(savedPhases: SavedEstimatedPhase[]): void {
//     this.phases.controls.forEach((control, index) => {
//       const savedPhase = savedPhases[index];
//       if (savedPhase) {
//         control.patchValue({
//           start_date: this.formatDateForInput(savedPhase.start_date),
//           end_date: this.formatDateForInput(savedPhase.end_date),
//           estimated_hours: savedPhase.estimated_hours
//         });
//       }
//     });
//   }

//   private formatDateForInput(dateString: string | null | undefined): string {
//     if (!dateString) return '';
//     let cleanDate = dateString.trim().substring(0, 10);
//     cleanDate = cleanDate.replace(/\//g, '-');
//     const parts = cleanDate.split('-');
//     if (parts.length === 3) {
//       if (parts[0].length === 2 && parts[2].length === 4) {
//         return `${parts[2]}-${parts[1]}-${parts[0]}`; 
//       }
//     }
//     return cleanDate;
//   }

//   onSaveDraft(): void {
//     if (this.estimationForm.invalid) {
//       this.estimationForm.markAllAsTouched();
//       return;
//     }

//     this.isSubmitting.set(true);
//     const payload = this.getFormattedPayload();

//     this.estimationService.saveEstimation(this.requirementId(), payload).subscribe({
//       next: (response) => {
//         // 🚀 4. Uso del servicio: Toast de éxito
//         this.notificationService.toastSuccess('Estimación guardada correctamente.');
//         this.hasSavedData.set(true);
//         this.isSubmitting.set(false);
//         console.log('Estimation guardada correctamente:', response);
//       },
//       error: (err) => {
//         let errorMsg = 'Error al procesar la solicitud.';
//         if (err.error?.errors) {
//           errorMsg = Object.values(err.error.errors).flat().join('<br>');
//         } else if (err.error?.message) {
//           errorMsg = err.error.message;
//         }
//         // 🚀 5. Uso del servicio: Mostrar advertencia de validación
//         this.notificationService.showWarning('Error de Validación', errorMsg);
//         this.isSubmitting.set(false);
//       }
//     });
//   }
  
//   async onConfirmClosePhase(): Promise<void> {
//       // Validacion estricta 
//       // Evaluamos el chronologyValidator y campos vacíos antes de pedir justificación
//       if (this.estimationForm.invalid) {
//         this.estimationForm.markAllAsTouched();
        
        
//         // Uso de tu servicio centralizado para bloquear la acción
//         this.notificationService.showWarning(
//           'Error de Cronograma', 
//           'Existen fechas inválidas o rupturas de secuencia. Corrija los errores antes de cerrar la fase definitivamente.'
//         );
//         return; // Abortamos la ejecución del prompt
//       }

//       const htmlMsg = `El requerimiento quedará <strong>bloqueado permanentemente</strong>.<br><br>Por favor, ingrese la justificación técnica para este cierre:`;
      
//       // Prompt de Justificación usando tu NotificationService
//       const justificacion = await this.notificationService.promptText(
//         'Cerrar Fase de Planificación', 
//         htmlMsg, 
//         'Ej: Planificación aprobada en comité CSPE...'
//       );

//       if (justificacion) {
//         this.executeHardGate(justificacion);
//       }
//   } 

//   private executeHardGate(justificationText: string): void {
//     // Doble chequeo de seguridad frontend
//     if (this.estimationForm.invalid) return;

//     this.isSubmitting.set(true);
    
//     // 🚀 1. Obtenemos los datos actuales de la interfaz que el usuario intenta cerrar
//     const payload = this.getFormattedPayload();

//     // 🚀 2. Ejecución concatenada (RxJS switchMap)
//     // Primero intentamos guardar la estimación. Esto obligará al backend a ejecutar el 'chronologyValidator'.
//     this.estimationService.saveEstimation(this.requirementId(), payload)
//       .pipe(
//         // 🚀 3. Si el guardado es válido y exitoso, pasamos al cierre de la fase automáticamente
//         switchMap(() => this.estimationService.closePlanningPhase(this.requirementId(), justificationText))
//       )
//       .subscribe({
//         next: (response) => {
//           // Uso de tu servicio: Mostrar éxito de operación crítica
//           this.notificationService.showSuccess('¡Fase Cerrada!', 'El requerimiento está ahora inmutable. Lista para ATF.');
//           this.estimationForm.disable(); 
//           this.isSubmitting.set(false);
//           this.hasSavedData.set(false); 
//           console.log('Fase cerrada correctamente:', response);
//         },
//         error: (err) => {
//           // 🚀 4. Si la fecha era inválida (ej. anterior a la creación), el backend abortará el guardado
//           // y el switchMap JAMÁS se ejecutará, protegiendo el requerimiento de un cierre corrupto.
//           let errorMsg = 'Error al procesar la solicitud.';
//           if (err.error?.errors) {
//             errorMsg = Object.values(err.error.errors).flat().join('<br>');
//           } else if (err.error?.message) {
//             errorMsg = err.error.message;
//           }
          
//           // Uso de tu servicio: Mostrar el error exacto y detener el proceso
//           this.notificationService.showWarning('Operación Rechazada', errorMsg);
//           this.isSubmitting.set(false);
//         }
//       });
//   }

//   private getFormattedPayload(): EstimationPayload {
//     const rawData = this.estimationForm.getRawValue();
//     return {
//       requirement_id: this.requirementId(),
//       phases: rawData.phases
//     };
//   }

//   goBackToDashboard(): void {
//     this.router.navigate(['/dashboard']);
//   }
// }


import { Component, inject, input, output, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormArray, ReactiveFormsModule, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { EstimationService } from '../../data-access/services/estimation';
import { EstimationPayload, SavedEstimatedPhase } from '../../data-access/models/requirement.model';
import { NotificationService } from '@app/workflow';
import { switchMap } from 'rxjs/operators';

@Component({
  selector: 'lib-estimation-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './estimation-form.component.html',
  styleUrls: ['./estimation-form.component.scss'] 
})
export class EstimationFormComponent implements OnInit {

  private fb = inject(FormBuilder);
  private estimationService = inject(EstimationService);
  private notificationService = inject(NotificationService); 

  // ==========================================
  // 1. ENTRADAS Y SALIDAS (Inputs / Outputs)
  // ==========================================
  requirementId = input.required<string>();
  requirementCode = input.required<string>(); 
  requirementCreationDate = input.required<string>(); 
  isPlanningClosed = input<boolean>(false);
  
  closeModal = output<void>();
  estimationSaved = output<void>();


  // ==========================================
  // 2. ESTADO LOCAL REACTIVO
  // ==========================================
  isLocked = signal<boolean>(false);               
  isLoadingData = signal<boolean>(true);           
  isSubmitting = signal<boolean>(false);
  hasSavedData = signal<boolean>(false); 

  private defaultPhases = ['ATF', 'DISENO', 'CONSTRUCCION', 'PRUEBAS', 'CERTIFICACION', 'IMPLEMENTACION'];

  // ==========================================
  // 3. CONFIGURACIÓN DEL FORMULARIO
  // ==========================================
  // 🚀 CORRECCIÓN: Solo declaramos el formulario aquí, sin inicializarlo.
  estimationForm!: FormGroup;


  ngOnInit(): void {
    // 🚀 CORRECCIÓN: Inicializamos el formulario aquí, donde los Signals ya tienen datos.
    this.estimationForm = this.fb.group({
      phases: this.fb.array([])
    }, { validators: (control: AbstractControl) => this.chronologyValidator(control) });

    // Inicializamos las fases por defecto
    this.initializeForm();
    
    // Procedemos a cargar los datos del backend
    this.loadEstimationData();
  }

  get phases(): FormArray {
    return this.estimationForm.get('phases') as FormArray;
  }

  getPhaseLabel(phaseCode: string): string {
    const labels: Record<string, string> = {
      'ATF': 'ATF',
      'DISENO': 'Diseño Técnico',
      'CONSTRUCCION': 'Construcción',
      'PRUEBAS': 'Pruebas Integrales',
      'CERTIFICACION': 'Certificación',
      'IMPLEMENTACION': 'Implementación'
    };
    return labels[phaseCode] || phaseCode;
  }

  private initializeForm(): void {
    this.defaultPhases.forEach(phaseName => {
      const phaseGroup = this.fb.group({
        phase_name: [{ value: phaseName, disabled: true }], 
        start_date: ['', Validators.required],
        end_date: ['', Validators.required],
        estimated_hours: ['', [Validators.required, Validators.min(1)]]
      });
      this.phases.push(phaseGroup);
    });
  }

  // ==========================================
  // 4. VALIDACIÓN CRUZADA AVANZADA
  // ==========================================
  private chronologyValidator(control: AbstractControl): ValidationErrors | null {
    const phasesArray = control.get('phases') as FormArray;
    if (!phasesArray) return null;

    let previousStartDate: Date | null = null;
    
    const creationDate = new Date(this.requirementCreationDate());
    creationDate.setHours(0, 0, 0, 0);

    for (let i = 0; i < phasesArray.length; i++) {
      const phaseGroup = phasesArray.at(i);
      const startVal = phaseGroup.get('start_date')?.value;
      const endVal = phaseGroup.get('end_date')?.value;
      const phaseName = phaseGroup.get('phase_name')?.value || `Fase ${i + 1}`;

      if (startVal && endVal) {
        const currentStart = new Date(startVal);
        const currentEnd = new Date(endVal);

        if (currentStart < creationDate) {
          return { chronologyError: `Error en ${phaseName}: La fecha de inicio no puede ser anterior a la creación del requerimiento.` };
        }

        if (currentEnd < currentStart) {
          return { chronologyError: `Error en ${phaseName}: La fecha de fin no puede ser anterior a la fecha de inicio.` };
        }

        if (previousStartDate !== null && currentStart < previousStartDate) {
          return { chronologyError: `Ruptura de secuencia: ${phaseName} no puede iniciar antes de la fecha de inicio de su predecesora.` };
        }
        previousStartDate = currentStart;
      }
    }
    return null;
  }
  
  // ==========================================
  // 5. CARGA Y FORMATEO DE DATOS
  // ==========================================
  private loadEstimationData(): void {
    this.isLoadingData.set(true);
    
    this.estimationService.getEstimationDetails(this.requirementId()).subscribe({
      next: (response) => {
        const data = response.data;
        this.isLocked.set(data.is_locked);

        if (data.estimation && data.estimation.estimated_phases) {
          this.patchFormWithSavedData(data.estimation.estimated_phases);
          this.hasSavedData.set(true);
        }

        if (data.is_locked) {
          this.estimationForm.disable(); 
        }

        this.isLoadingData.set(false);
      },
      error: (err) => {
        this.notificationService.showError('Error', 'Error al cargar los datos de estimación.');
        this.isLoadingData.set(false);
        console.error('Error al cargar datos:', err);
      }
    });
  }

  private patchFormWithSavedData(savedPhases: SavedEstimatedPhase[]): void {
    this.phases.controls.forEach((control, index) => {
      const savedPhase = savedPhases[index];
      if (savedPhase) {
        control.patchValue({
          start_date: this.formatDateForInput(savedPhase.start_date),
          end_date: this.formatDateForInput(savedPhase.end_date),
          estimated_hours: savedPhase.estimated_hours
        });
      }
    });
  }

  private formatDateForInput(dateString: string | null | undefined): string {
    if (!dateString) return '';
    let cleanDate = dateString.trim().substring(0, 10);
    cleanDate = cleanDate.replace(/\//g, '-');
    const parts = cleanDate.split('-');
    if (parts.length === 3) {
      if (parts[0].length === 2 && parts[2].length === 4) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`; 
      }
    }
    return cleanDate;
  }

  private getFormattedPayload(): EstimationPayload {
    const rawData = this.estimationForm.getRawValue();
    return {
      requirement_id: this.requirementId(),
      phases: rawData.phases
    };
  }

  // ==========================================
  // 6. LÓGICA DE GUARDADO Y CIERRE (HARD GATE)
  // ==========================================
  onSaveDraft(): void {
    if (this.estimationForm.invalid) {
      this.estimationForm.markAllAsTouched();
      const globalError = this.estimationForm.errors?.['chronologyError'];
      if (globalError) {
        this.notificationService.showWarning('Secuencia Inválida', globalError);
      }
      return;
    }

    this.isSubmitting.set(true);
    const payload = this.getFormattedPayload();

    this.estimationService.saveEstimation(this.requirementId(), payload).subscribe({
      next: (response) => {
        this.notificationService.toastSuccess('Estimación guardada correctamente.');
        this.hasSavedData.set(true);
        this.isSubmitting.set(false);
        this.estimationSaved.emit(); 
        console.log('Estimation guardada correctamente:', response);
      },
      error: (err) => {
        let errorMsg = 'Error al procesar la solicitud.';
        if (err.error?.errors) {
          errorMsg = Object.values(err.error.errors).flat().join('<br>');
        } else if (err.error?.message) {
          errorMsg = err.error.message;
        }
        this.notificationService.showWarning('Error de Validación', errorMsg);
        this.isSubmitting.set(false);
      }
    });
  }
  
  async onConfirmClosePhase(): Promise<void> {
      if (this.estimationForm.invalid) {
        this.estimationForm.markAllAsTouched();
        
        const globalError = this.estimationForm.errors?.['chronologyError'];
        const msg = globalError || 'Existen fechas inválidas o campos en blanco. Corrija los errores antes de cerrar la fase.';
        
        this.notificationService.showWarning('Error de Cronograma', msg);
        return; 
      }

      const htmlMsg = `El requerimiento quedará <strong>bloqueado permanentemente</strong>.<br><br>Por favor, ingrese la justificación técnica para este cierre:`;
      
      const justificacion = await this.notificationService.promptText(
        'Cerrar Fase de Planificación', 
        htmlMsg, 
        'Ej: Planificación aprobada en comité CSPE...'
      );

      if (justificacion) {
        this.executeHardGate(justificacion);
      }
  } 

  private executeHardGate(justificationText: string): void {
    if (this.estimationForm.invalid) return;

    this.isSubmitting.set(true);
    const payload = this.getFormattedPayload();

    this.estimationService.saveEstimation(this.requirementId(), payload)
      .pipe(
        switchMap(() => this.estimationService.closePlanningPhase(this.requirementId(), justificationText))
      )
      .subscribe({
        next: (response) => {
          this.notificationService.showSuccess('¡Fase Cerrada!', 'El requerimiento está ahora inmutable. Listo para ATF.');
          this.estimationForm.disable(); 
          this.isSubmitting.set(false);
          this.hasSavedData.set(false); 
          this.estimationSaved.emit(); 
          this.closeModal.emit(); 
          console.log('Fase cerrada correctamente:', response);
        },
        error: (err) => {
          let errorMsg = 'Error al procesar la solicitud.';
          if (err.error?.errors) {
            errorMsg = Object.values(err.error.errors).flat().join('<br>');
          } else if (err.error?.message) {
            errorMsg = err.error.message;
          }
          
          this.notificationService.showWarning('Operación Rechazada', errorMsg);
          this.isSubmitting.set(false);
        }
      });
  }
}