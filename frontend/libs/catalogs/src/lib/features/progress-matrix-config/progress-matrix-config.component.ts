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

type BackendMilestoneResponse = {
  id?: string;
  milestone_id?: string;
  name?: string;
  weight_percentage?: number;
  milestone?: {
    id?: string;
    name?: string;
  };
};


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
  // public totalSumSignal = computed(() => {
  //   return this.calculateSum();
  // });
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

  // private populateForm(milestones: Milestone[]): void {
  //   this.milestonesFormArray.clear();
  //   milestones.forEach(m => {
  //     this.milestonesFormArray.push(this.fb.group({
  //       milestone_id: [m.id], // 👈 Clave foránea exacta exigida por el FormRequest
  //       name: [m.name],
  //       // FS-03: Mínimo 0.01 exigido por regla de negocio
  //       weight: [m.weight, [Validators.required, Validators.min(0.01)]]
  //     }));
  //   });
  //   this.updateSumSignal();
  // }

  // private populateForm(milestones: Milestone[]): void {
  //   this.milestonesFormArray.clear(); // Destruye controles (Angular ahora destruirá el HTML también)

  //   milestones.forEach(m => {
  //     // RN: Si el peso es 0 o indefinido, lo pasamos como null para forzar su llenado manual
  //     const initialWeight = (m.weight && m.weight > 0) ? m.weight : null;

  //     this.milestonesFormArray.push(this.fb.group({
  //       milestone_id: [m.id],
  //       name: [m.name],
  //       // RN Institucional: No se permiten campos vacíos ni valores en 0
  //       weight: [initialWeight, [Validators.required, Validators.min(0.01)]]
  //     }));
  //   });

  //   this.updateTotals(); // Forzamos el recálculo inicial
  // }

  // private populateForm(milestones: Milestone[]): void {
  //   this.milestonesFormArray.clear();

  //   milestones.forEach((m) => {

  //     const rawData = m as unknown as Record<string, unknown>;

  //     // Extracción segura del UUID inyectado por Laravel
  //     const correctMilestoneId =
  //       m.id ?? (rawData['milestone_id'] as string) ?? null;

  //     const initialWeight = m.weight && m.weight > 0 ? m.weight : null;

  //     this.milestonesFormArray.push(
  //       this.fb.group({
  //         milestone_id: [correctMilestoneId, [Validators.required]],
  //         name: [m.name],
  //         weight: [initialWeight, [Validators.required, Validators.min(0.01)]],
  //       }),
  //     );
  //   });

  //   this.updateTotals(); // Forzamos el recálculo inicial
  // }

  // private populateForm(milestones: Milestone[]): void {
  //   this.milestonesFormArray.clear();

  //   milestones.forEach((m) => {
  //     // 1. Casting seguro para evadir el error de TypeScript
  //     const rawData = m as unknown as Record<string, unknown>;
  //     const nestedMilestone = rawData['milestone'] as
  //       | Record<string, unknown>
  //       | undefined;

  //     // 2. Extracción segura del ID
  //     const correctMilestoneId =
  //       m.id ?? (rawData['milestone_id'] as string) ?? null;

  //     // 3. Extracción segura del Nombre (Corrige el error de "Hito Técnico")
  //     const correctName =
  //       m.name ??
  //       (rawData['name'] as string) ??
  //       (nestedMilestone?.['name'] as string) ??
  //       'Hito Técnico';

  //     // 4. Extracción segura del Peso (Si Laravel envía 'weight_percentage' en lugar de 'weight')
  //     const weightVal =
  //       m.weight ?? (rawData['weight_percentage'] as number) ?? 0;
  //     const initialWeight = weightVal > 0 ? weightVal : null;

  //     this.milestonesFormArray.push(
  //       this.fb.group({
  //         milestone_id: [correctMilestoneId, [Validators.required]],
  //         name: [correctName], // Asignación del nombre real
  //         weight: [initialWeight, [Validators.required, Validators.min(0.01)]],
  //       }),
  //     );
  //   });

  //   this.updateTotals();
  // }

  // private populateForm(milestones: Milestone[]): void {
  //   this.milestonesFormArray.clear(); 
    
  //   milestones.forEach(m => {
  //     // 1. Casting seguro hacia nuestro tipo auxiliar
  //     const rawData = (m as unknown) as BackendMilestoneResponse;
      
  //     // 2. Extracción limpia usando Optional Chaining (?.)
  //     const correctMilestoneId = m.id ?? rawData.milestone_id ?? rawData.milestone?.id ?? null;

  //     // 3. Extracción del nombre real (Corrige el fallo visual de "Hito Técnico")
  //     const correctName = m.name ?? rawData.name ?? rawData.milestone?.name ?? 'Hito Técnico';

  //     // 4. Extracción segura del Peso
  //     const weightVal = m.weight ?? rawData.weight_percentage ?? 0;
  //     const initialWeight = weightVal > 0 ? weightVal : null;

  //     this.milestonesFormArray.push(this.fb.group({
  //       milestone_id: [correctMilestoneId, [Validators.required]], 
  //       name: [correctName],
  //       weight: [initialWeight, [Validators.required, Validators.min(0.01)]] 
  //     }));
  //   });
    
  //   this.updateTotals();
  // }


  private populateForm(milestones: Milestone[]): void {
    this.milestonesFormArray.clear(); 

    // 1. Obtenemos el tipo de gestión actual seleccionado en el formulario
    // Aseguramos que esté en mayúsculas para que coincida con las llaves del diccionario
    const currentManagementType = (this.matrixForm.get('management_type')?.value || 'ROLES').toUpperCase();

    // 2. RN: Diccionario de Ordenamiento Canónico Estricto por Tipología.
    // Define el ciclo de vida exacto según las reglas de negocio del MDM.
    const canonicalOrders: Record<string, string[]> = {
      'ROLES': [
        'Requerimiento Creado', 'Estimacion Registrada', 'Análisis Técnico Funcional iniciado',
        'Análisis Técnico Funcional Completado', 'Diseño Técnico Iniciado', 'Diseño Técnico Completado',
        'Construcción roles Iniciado', 'Construcción roles Completado', 'Pruebas Integrales Iniciadas',
        'Pruebas Integrales Completadas', 'Certificación Roles Iniciada', 'Certificación Roles Completada',
        'PAP iniciado', 'PAP Completado', 'Asignación Usuarios Completado', 'Requerimiento Finalizado'
      ],
      'ENTREGABLES': [
        'Requerimiento Creado', 'Estimacion Registrada', 'Análisis Técnico Funcional iniciado',
        'Análisis Técnico Funcional Completado', 'Construcción Entregable Iniciado', 'Construcción Entregable Completado',
        'Certificación Entregable Iniciada', 'Certificación Entregable Completada', 'Requerimiento Finalizado'
      ],
      'MIXTO': [
        'Requerimiento Creado', 'Estimacion Registrada', 'Análisis Técnico Funcional iniciado',
        'Análisis Técnico Funcional Completado', 'Diseño Técnico Iniciado', 'Diseño Técnico Completado',
        'Construcción roles Iniciado', 'Construcción roles Completado', 'Construcción Entregable Iniciado',
        'Construcción Entregable Completado', 'Pruebas Integrales Iniciadas', 'Pruebas Integrales Completadas',
        'Certificación Roles Iniciada', 'Certificación Roles Completada', 'Certificación Entregable Iniciada',
        'Certificación Entregable Completada', 'PAP iniciado', 'PAP Completado', 'Asignación Usuarios Completado',
        'Requerimiento Finalizado'
      ]
    };

    // 3. Seleccionamos el array de orden correcto según el tipo actual (Fallback a MIXTO por seguridad)
    const activeCanonicalOrder = canonicalOrders[currentManagementType] || canonicalOrders['MIXTO'];

    // 4. Ordenamos el array interceptado comparando contra la lista maestra activa
    milestones.sort((a, b) => {
      const rawA = (a as unknown) as BackendMilestoneResponse;
      const rawB = (b as unknown) as BackendMilestoneResponse;
      
      const nameA = a.name ?? rawA.name ?? rawA.milestone?.name ?? '';
      const nameB = b.name ?? rawB.name ?? rawB.milestone?.name ?? '';

      // Búsqueda dinámica en el array activo
      let indexA = activeCanonicalOrder.findIndex(title => nameA.trim().toLowerCase() === title.trim().toLowerCase());
      let indexB = activeCanonicalOrder.findIndex(title => nameB.trim().toLowerCase() === title.trim().toLowerCase());

      // Elementos no coincidentes se envían al final
      if (indexA === -1) indexA = 999;
      if (indexB === -1) indexB = 999;

      return indexA - indexB;
    });

    // 5. Mapeo Defensivo hacia el FormArray
    milestones.forEach(m => {
      const rawData = (m as unknown) as BackendMilestoneResponse;
      
      const correctMilestoneId = m.id ?? rawData.milestone_id ?? rawData.milestone?.id ?? null;
      const correctName = m.name ?? rawData.name ?? rawData.milestone?.name ?? 'Hito Técnico';

      const weightVal = m.weight ?? rawData.weight_percentage ?? 0;
      const initialWeight = weightVal > 0 ? weightVal : null;

      this.milestonesFormArray.push(this.fb.group({
        milestone_id: [correctMilestoneId, [Validators.required]], 
        name: [correctName],
        weight: [initialWeight, [Validators.required, Validators.min(0.01)]] 
      }));
    });
    
    this.updateTotals();
  }



  /**
   * Establece las suscripciones reactivas a los cambios de valor del formulario.
   */
  // private setupFormListeners(): void {
  //   // Suscripción al cambio de tipo de gestión (Toggle Roles/Entregables/Mixto)
  //   this.matrixForm.get('management_type')?.valueChanges.subscribe(val => {
  //     this.currentManagementType.set(val as ManagementType);
  //     this.loadActiveMatrix(val as ManagementType);
  //   });

  //   // Suscripción profunda a los cambios de pesos para forzar el recálculo de la Signal
  //   this.matrixForm.get('milestones')?.valueChanges.subscribe(() => {
  //       this.updateSumSignal();
  //   });
  // }

  private setupFormListeners(): void {
    this.matrixForm.get('management_type')?.valueChanges.subscribe((val) => {
      this.currentManagementType.set(val as ManagementType);
      this.loadActiveMatrix(val as ManagementType);
    });

    // 3. ACTUALIZACIÓN REACTIVA EXPLÍCITA
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
    this.currentTotalSum.set(Math.round(sum * 100) / 100); // Actualiza la Signal real
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
   * Prepara el payload y lo envía al servicio para consolidar una nueva versión inmutable.
   */
  public publishNewVersion(): void {
    if (this.matrixForm.invalid) {
      this.matrixForm.markAllAsTouched();
      this.notificationService.showWarning(
        'Validación Pendiente',
        'Verifique que la sumatoria alcance exactamente el 100.00% y no existan campos vacíos.',
      );
      return;
    }

    const payload = this.matrixForm.value;

    this.progressMatrixService.publishMatrix(payload).subscribe({
      next: (res) => {
        // Invocación del servicio centralizado para mensaje de éxito
        this.notificationService.showSuccess('Versión Publicada', res.message);
        this.loadActiveMatrix(this.currentManagementType());
      },
      error: (err) => {
        // Captura del Hard Gate Server-Side (422 Unprocessable Entity)
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
