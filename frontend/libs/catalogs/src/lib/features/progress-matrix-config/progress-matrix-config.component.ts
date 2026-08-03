// // frontend/libs/catalogs/src/lib/features/progress-matrix-config/progress-matrix-config.component.ts

// import { Component, OnInit, computed, inject, signal, Output, EventEmitter } from '@angular/core';
// import { CommonModule } from '@angular/common';
// import {
//   ReactiveFormsModule,
//   FormBuilder,
//   FormGroup,
//   FormArray,
//   Validators,
// } from '@angular/forms';

// import { ProgressMatrixService } from '../../data-access/services/progress-matrix.service';
// import {
//   ProgressMatrix,
//   Milestone,
//   ManagementType,
// } from '../../data-access/models/progress-matrix.model';

// import { NotificationService } from '@ecosgrti/workflow';


// @Component({
//   selector: 'lib-progress-matrix-config',
//   standalone: true,
//   imports: [CommonModule, ReactiveFormsModule],
//   templateUrl: './progress-matrix-config.component.html',
//   styleUrls: ['./progress-matrix-config.component.scss'],
// })
// export class ProgressMatrixConfigComponent implements OnInit {
//   // Inyección de dependencias estructurada
//   private readonly fb = inject(FormBuilder);
//   private readonly progressMatrixService = inject(ProgressMatrixService);
//   private readonly notificationService = inject(NotificationService);

//   // Formulario reactivo principal que orquesta la matriz
//   public matrixForm!: FormGroup;

//   // Signal para gobernar el estado del tipo de gestión seleccionado ('ROLES' | 'ENTREGABLES' | 'MIXTO')
//   public currentManagementType = signal<ManagementType>('ENTREGABLES');

//   // Signal para almacenar la fotografía inmutable de la matriz activa actual
//   public activeMatrix = signal<ProgressMatrix | null>(null);

//   // Signal computada para calcular la sumatoria en tiempo real aplicando la fórmula de precisión decimal
//   // public totalSumSignal = computed(() => {
//   //   return this.calculateSum();
//   // });
//   public currentTotalSum = signal<number>(0);

//   // Signal computada para determinar la diferencia porcentual faltante o excedente en la UI
//   public differenceSignal = computed(() => {
//     return Math.round((100.0 - this.currentTotalSum()) * 100) / 100;
//   });

  
//   @Output() closeModal = new EventEmitter<void>();


//   ngOnInit(): void {
//     this.initForm();
//     this.loadActiveMatrix(this.currentManagementType());
//     this.setupFormListeners();
//   }

//   /**
//    * Inicializa el formulario reactivo aplicando el validador estricto a nivel de FormGroup
//    * para garantizar el Hard Gate matemático antes del envío.
//    */
//   private initForm(): void {
//     this.matrixForm = this.fb.group({
//       management_type: [this.currentManagementType(), [Validators.required]],
//       milestones: this.fb.array([]),
//     });
//   }

//   /**
//    * Getter de conveniencia para acceder al FormArray de hitos desde la plantilla HTML.
//    */
//   get milestonesFormArray(): FormArray {
//     return this.matrixForm.get('milestones') as FormArray;
//   }

//   /**
//    * Obtiene la matriz activa desde el backend y pobla el formulario reactivo.
//    * Utiliza el NotificationService centralizado para el manejo de errores.
//    */
//   public loadActiveMatrix(type: ManagementType): void {
//     this.progressMatrixService.getActiveMatrix(type).subscribe({
//       next: (matrix) => {
//         this.activeMatrix.set(matrix);
//         this.populateForm(matrix.milestones);
//       },
//       error: (err) => {
//         console.error('Error al cargar la matriz activa', err);
//         this.notificationService.showError(
//           'Error de Conexión',
//           'No se pudo cargar la configuración de la matriz activa.',
//         );
//       },
//     });
//   }

//   /**
//    * Transforma el arreglo de hitos recibido del servidor en AbstractControls dentro del FormArray.
//    */

//   private populateForm(milestones: Milestone[]): void {
//     this.milestonesFormArray.clear(); 

//     // El frontend es totalmente agnóstico del orden; confía en el índice de la base de datos
//     milestones.forEach(m => {
//       this.milestonesFormArray.push(this.fb.group({
//         milestone_id: [m.id, [Validators.required]], 
//         name: [m.name],
//         weight: [m.weight, [Validators.required, Validators.min(0.01)]] 
//       }));
//     });
    
//     this.updateTotals();
//   }

//   /**
//    * Establece las suscripciones reactivas a los cambios de valor del formulario.
//    */
  

//   private setupFormListeners(): void {
//     this.matrixForm.get('management_type')?.valueChanges.subscribe((val) => {
//       this.currentManagementType.set(val as ManagementType);
//       this.loadActiveMatrix(val as ManagementType);
//     });

//     // 3. ACTUALIZACIÓN REACTIVA EXPLÍCITA
//     this.matrixForm.get('milestones')?.valueChanges.subscribe(() => {
//       this.updateTotals();
//     });
//   }

//   /**
//    * Disparador manual para refrescar el estado de las Signals dependientes de valueChanges.
//    */
//   private updateTotals(): void {
//     const milestones = this.milestonesFormArray.getRawValue();
//     const sum = milestones.reduce(
//       (acc, curr) => acc + (Number(curr.weight) || 0),
//       0,
//     );
//     this.currentTotalSum.set(Math.round(sum * 100) / 100); // Actualiza la Signal real
//   }

//   /**
//    * Ejecuta la suma estricta de los pesos iterando sobre el FormArray,
//    * aplicando redondeo explícito para mitigar imprecisiones de coma flotante (IEEE 754).
//    */
//   public calculateSum(): number {
//     if (!this.matrixForm) return 0;
//     const milestones = this.milestonesFormArray.value as Milestone[];
//     const sum = milestones.reduce(
//       (acc, curr) => acc + (Number(curr.weight) || 0),
//       0,
//     );
//     return Math.round(sum * 100) / 100;
//   }

//   /**
//    * Prepara el payload y lo envía al servicio para consolidar una nueva versión inmutable.
//    */
//   public publishNewVersion(): void {
//     if (this.matrixForm.invalid) {
//       this.matrixForm.markAllAsTouched();
//       this.notificationService.showWarning(
//         'Validación Pendiente',
//         'Verifique que la sumatoria alcance exactamente el 100.00% y no existan campos vacíos.',
//       );
//       return;
//     }

//     const payload = this.matrixForm.value;

//     this.progressMatrixService.publishMatrix(payload).subscribe({
//       next: (res) => {
//         // Invocación del servicio centralizado para mensaje de éxito
//         this.notificationService.showSuccess('Versión Publicada', res.message);
//         this.loadActiveMatrix(this.currentManagementType());
//       },
//       error: (err) => {
//         // Captura del Hard Gate Server-Side (422 Unprocessable Entity)
//         const msg =
//           err.error?.message ||
//           'Inconsistencia matemática detectada por el servidor.';
//         this.notificationService.showError('Publicación Rechazada', msg);
//       },
//     });
//   }

//   public closeComponent(): void {
//     this.closeModal.emit();
//   }
// }


// frontend/libs/catalogs/src/lib/features/progress-matrix-config/progress-matrix-config.component.ts

import { Component, OnInit, computed, inject, signal, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  ReactiveFormsModule,
  FormBuilder,
  FormGroup,
  FormArray,
  Validators,
} from '@angular/forms';

import { ProgressMatrixService } from '../../data-access/services/progress-matrix.service';
import {
  ProgressMatrix,
  Milestone,
  ManagementType,
} from '../../data-access/models/progress-matrix.model';

import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-progress-matrix-config',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './progress-matrix-config.component.html',
  styleUrls: ['./progress-matrix-config.component.scss'],
})
export class ProgressMatrixConfigComponent implements OnInit {
  // Inyección de dependencias estructurada
  private readonly fb = inject(FormBuilder);
  private readonly progressMatrixService = inject(ProgressMatrixService);
  private readonly notificationService = inject(NotificationService);

  // Formulario reactivo principal que orquesta la matriz
  public matrixForm!: FormGroup;

  // Signal para gobernar el estado del tipo de gestión seleccionado ('ROLES' | 'ENTREGABLES' | 'MIXTO')
  public currentManagementType = signal<ManagementType>('ENTREGABLES');

  // Signal para almacenar la fotografía inmutable de la matriz activa actual
  public activeMatrix = signal<ProgressMatrix | null>(null);

  // Signal computada para calcular la sumatoria en tiempo real aplicando la fórmula de precisión decimal
  public currentTotalSum = signal<number>(0);

  // Signal computada para determinar la diferencia porcentual faltante o excedente en la UI
  public differenceSignal = computed(() => {
    return Math.round((100.0 - this.currentTotalSum()) * 100) / 100;
  });

  @Output() closeModal = new EventEmitter<void>();

  ngOnInit(): void {
    this.initForm();
    this.loadActiveMatrix(this.currentManagementType());
    this.setupFormListeners();
  }

  /**
   * Inicializa el formulario reactivo aplicando el validador estricto a nivel de FormGroup
   * para garantizar el Hard Gate matemático antes del envío.
   */
  private initForm(): void {
    this.matrixForm = this.fb.group({
      management_type: [this.currentManagementType(), [Validators.required]],
      milestones: this.fb.array([]),
    });
  }

  /**
   * Getter de conveniencia para acceder al FormArray de hitos desde la plantilla HTML.
   */
  get milestonesFormArray(): FormArray {
    return this.matrixForm.get('milestones') as FormArray;
  }

  /**
   * Obtiene la matriz activa desde el backend y pobla el formulario reactivo.
   * Utiliza el NotificationService centralizado para el manejo de errores.
   */
  public loadActiveMatrix(type: ManagementType): void {
    this.progressMatrixService.getActiveMatrix(type).subscribe({
      next: (matrix) => {
        this.activeMatrix.set(matrix);
        this.populateForm(matrix.milestones);
      },
      error: (err) => {
        console.error('Error al cargar la matriz activa', err);
        this.notificationService.showError(
          'Error de Conexión',
          'No se pudo cargar la configuración de la matriz activa.',
        );
      },
    });
  }

  /**
   * Transforma el arreglo de hitos recibido del servidor en AbstractControls dentro del FormArray.
   */
  private populateForm(milestones: Milestone[]): void {
    this.milestonesFormArray.clear(); 

    milestones.forEach(m => {
      this.milestonesFormArray.push(this.fb.group({
        milestone_id: [m.id, [Validators.required]], 
        name: [m.name],
        weight: [m.weight, [Validators.required, Validators.min(0.01)]] 
      }));
    });
    
    this.updateTotals();
  }

  /**
   * Establece las suscripciones reactivas a los cambios de valor del formulario.
   */
  private setupFormListeners(): void {
    this.matrixForm.get('management_type')?.valueChanges.subscribe((val) => {
      this.currentManagementType.set(val as ManagementType);
      this.loadActiveMatrix(val as ManagementType);
    });

    this.matrixForm.get('milestones')?.valueChanges.subscribe(() => {
      this.updateTotals();
    });
  }

  /**
   * Disparador manual para refrescar el estado de las Signals dependientes de valueChanges.
   */
  private updateTotals(): void {
    const milestones = this.milestonesFormArray.getRawValue();
    const sum = milestones.reduce(
      (acc, curr) => acc + (Number(curr.weight) || 0),
      0,
    );
    this.currentTotalSum.set(Math.round(sum * 100) / 100);
  }

  /**
   * Ejecuta la suma estricta de los pesos iterando sobre el FormArray,
   * aplicando redondeo explícito para mitigar imprecisiones de coma flotante (IEEE 754).
   */
  public calculateSum(): number {
    if (!this.matrixForm) return 0;
    const milestones = this.milestonesFormArray.value as Milestone[];
    const sum = milestones.reduce(
      (acc, curr) => acc + (Number(curr.weight) || 0),
      0,
    );
    return Math.round(sum * 100) / 100;
  }

  /**
   * Valida de manera estricta que cada control del FormArray posea un número válido y >= 0.01
   */
  public validateMilestonesData(): boolean {
    const controls = this.milestonesFormArray.controls;
    
    if (controls.length === 0) return false;

    for (const control of controls) {
      const weightValue = Number(control.get('weight')?.value);
      
      if (isNaN(weightValue) || weightValue < 0.01) {
        return false;
      }
    }
    return true;
  }

  /**
   * Getter computado y libre de falsos positivos para gobernar el estado de la UI
   */
  public get isSumValid(): boolean {
    const total = this.currentTotalSum();
    const sumMatches = total === 100.00;
    const fieldsAreValid = this.validateMilestonesData();

    return sumMatches && fieldsAreValid;
  }

  /**
   * Prepara el payload y lo envía al servicio para consolidar una nueva versión inmutable.
   */
  public publishNewVersion(): void {
    if (!this.isSumValid || this.matrixForm.invalid) {
      this.matrixForm.markAllAsTouched();
      this.notificationService.showWarning(
        'Validación Pendiente',
        'Verifique que la sumatoria alcance exactamente el 100.00% y no existan campos vacíos o menores a 0.01.',
      );
      return;
    }

    const payload = this.matrixForm.value;

    this.progressMatrixService.publishMatrix(payload).subscribe({
      next: (res) => {
        this.notificationService.showSuccess('Versión Publicada', res.message);
        this.loadActiveMatrix(this.currentManagementType());
      },
      error: (err) => {
        const msg =
          err.error?.message ||
          'Inconsistencia matemática detectada por el servidor.';
        this.notificationService.showError('Publicación Rechazada', msg);
      },
    });
  }

  public closeComponent(): void {
    this.closeModal.emit();
  }
}
