import { Component, inject, input, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormArray, ReactiveFormsModule, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import Swal from 'sweetalert2';
import { Router } from '@angular/router';
import { EstimationService } from '../../data-access/services/estimation';
import { EstimationPayload, SavedEstimatedPhase } from '../../data-access/models/requirement.model';

@Component({
  selector: 'lib-estimation-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './estimation-form.component.html',
  styleUrls: ['./estimation-form.component.scss']
})
export class EstimationFormComponent implements OnInit {

  // Inyecciones
  private fb = inject(FormBuilder);
  private estimationService = inject(EstimationService);
  private router = inject(Router);

  // Input Signal: Recibe el ID del requerimiento desde el componente padre
  requirementId = input.required<string>();

  // Signals para controlar el estado de la interfaz  
  requirementRrti = signal<string>('Cargando...'); // Para mostrar en el título
  isLocked = signal<boolean>(false);               // Controla el candado global
  isLoadingData = signal<boolean>(true);           // Controla el loader inicial

  // Input Signal: Recibe el Justificación desde el componente padre
  justification = input.required<string>();

  // Manejo de Estado Local con Signals
  isSubmitting = signal<boolean>(false);
  successMessage = signal<string | null>(null);
  errorMessage = signal<string | null>(null);
  
  // Nueva variable para saber si la data ya existe en BD (para mostrar el botón de cerrar fase)
  hasSavedData = signal<boolean>(false); 

  // Fases predefinidas exigidas por el "Camino de Hierro"
  private defaultPhases = [
    'ATF',
    'DISENO',
    'CONSTRUCCION',
    'PRUEBAS',
    'CERTIFICACION',
    'IMPLEMENTACION'
  ];

  // Función para obtener el label de la fase basado en el código
  getPhaseLabel(phaseCode: string): string {
    const labels: Record<string, string> = {
      'ATF': 'ATF',
      'DISENO': 'Diseño',
      'CONSTRUCCION': 'Construcción',
      'PRUEBAS': 'Pruebas Integrales',
      'CERTIFICACION': 'Certificación',
      'IMPLEMENTACION': 'Implementación'
    };
    return labels[phaseCode] || phaseCode;
  }

  // Definición del Formulario Reactivo
  estimationForm: FormGroup = this.fb.group({
    phases: this.fb.array([])
  }, { validators: this.chronologyValidator });

  constructor() {
    this.initializeForm();
  }

  ngOnInit(): void {
    this.loadEstimationData();
  }

  // Getter (Signal-like) para acceder fácilmente al FormArray en el HTML
  get phases(): FormArray {
    return this.estimationForm.get('phases') as FormArray;
  }

  /**
   * Inicializa el FormArray con las 6 fases estáticas
   */
  private initializeForm(): void {
    this.defaultPhases.forEach(phaseName => {
      const phaseGroup = this.fb.group({
        phase_name: [{ value: phaseName, disabled: true }], // Solo lectura para el usuario
        start_date: ['', Validators.required],
        end_date: ['', Validators.required],
        estimated_hours: ['', [Validators.required, Validators.min(1)]]
      });
      this.phases.push(phaseGroup);
    });
  }

  /**
   * Validador cruzado (Frontend) para guiar al usuario antes de tocar el Backend.
   * Verifica solapamientos básicos entre fechas consecutivas.
   */
  private chronologyValidator(control: AbstractControl): ValidationErrors | null {
    const phasesArray = control.get('phases') as FormArray;
    if (!phasesArray) return null;

    for (let i = 1; i < phasesArray.length; i++) {
      const prevEnd = phasesArray.at(i - 1).get('end_date')?.value;
      const currStart = phasesArray.at(i).get('start_date')?.value;

      // Si hay fechas y la fecha de inicio actual es menor que el fin de la anterior, hay error
      if (prevEnd && currStart && new Date(currStart) < new Date(prevEnd)) {
        return { chronologyError: `La fase ${i + 1} no puede iniciar antes de que culmine la fase ${i}.` };
      }
    }
    return null;
  }
  
  /**
   * Consulta al Backend el estado actual del requerimiento al abrir la vista.
   */
  private loadEstimationData(): void {
    this.isLoadingData.set(true);
    
    this.estimationService.getEstimationDetails(this.requirementId()).subscribe({
      next: (response) => {
        const data = response.data;
        
        // 1. Asignamos la identidad del requerimiento
        this.requirementRrti.set(data.rrti);
        this.isLocked.set(data.is_locked);

        // 2. Si ya hay una estimación (Borrador o Cerrada), hidratamos el formulario
        if (data.estimation && data.estimation.estimated_phases) {
          this.patchFormWithSavedData(data.estimation.estimated_phases);
          this.hasSavedData.set(true);
        }

        // 3. RN-Inmutabilidad: Si la fase PL está cerrada, bloqueamos el formulario por completo
        if (data.is_locked) {
          this.estimationForm.disable(); // Read-Only
        }

        this.isLoadingData.set(false);
      },
      error: (err) => {
        this.errorMessage.set('Error al cargar los datos del requerimiento.');
        this.isLoadingData.set(false);
        console.error('Error al obtener detalles de estimación:', err); // Log para debugging
      }
    });
  }

  /**
   * Mapea los datos de la base de datos a las filas del FormArray Reactivo
   */
  private patchFormWithSavedData(savedPhases: SavedEstimatedPhase[]): void {
    this.phases.controls.forEach((control, index) => {
      const savedPhase = savedPhases[index];

      if (savedPhase) {
        control.patchValue({
          // POR QUÉ: Pasamos las fechas por el "filtro" antes de inyectarlas al HTML
          start_date: this.formatDateForInput(savedPhase.start_date),
          end_date: this.formatDateForInput(savedPhase.end_date),
          estimated_hours: savedPhase.estimated_hours
        });
      }
    });
  }
  /**
   * Formatea una fecha en el formato YYYY-MM-DD
   */
  private formatDateForInput(dateString: string | null | undefined): string {
    if (!dateString) return '';
    
    // 1. Tomamos solo los primeros 10 caracteres (elimina horas como 15:30:00 si existen)
    let cleanDate = dateString.trim().substring(0, 10);
    
    // 2. Reemplazamos cualquier slash (/) por guion (-)
    cleanDate = cleanDate.replace(/\//g, '-');

    // 3. Detectamos si Laravel lo guardó invertido (ej. 31-12-2026) y lo enderezamos
    const parts = cleanDate.split('-');
    if (parts.length === 3) {
      // Si el primer bloque tiene 2 dígitos (Día o Mes), asumimos que el Año está al final
      if (parts[0].length === 2 && parts[2].length === 4) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`; // Lo forzamos a YYYY-MM-DD
      }
    }

    // Retorna el formato estandarizado
    return cleanDate;
  }

  /**
   * BOTÓN 1: Guardar Borrador (Solo guarda, no bloquea)
   */
  onSaveDraft(): void {
    if (this.estimationForm.invalid) {
      this.estimationForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    const payload = this.getFormattedPayload();

    this.estimationService.saveEstimation(this.requirementId(), payload).subscribe({
      next: (response) => {
        // Alerta suave porque es solo un guardado
        this.successMessage.set('Progreso guardado correctamente. Puede seguir editando.');
        this.hasSavedData.set(true); // Habilitamos el botón de cerrar fase
        this.isSubmitting.set(false);
        console.log('Respuesta del backend:', response); // Log para debugging
      },
      error: (err) => {
        this.errorMessage.set(err.error?.message || 'Error al guardar la estimación.');
        this.isSubmitting.set(false);
        console.error('Error al guardar la estimación:', err); // Log para debugging
      }
    });
  }

  /**
   * BOTÓN 2: Cerrar Fase (Hard Gate)
   */
  onConfirmClosePhase(): void {
    Swal.fire({
      title: 'Cerrar Fase de Planificación',
      html: `El requerimiento quedará <strong>bloqueado permanentemente</strong>.<br><br>Por favor, ingrese la justificación técnica para este cierre (mín. 10 caracteres):`,
      icon: 'warning',
      input: 'textarea', // Le pedimos a SweetAlert que muestre un campo de texto
      inputPlaceholder: 'Ej: Planificación aprobada en comité CSPE...',
      inputAttributes: {
        'aria-label': 'Justificación técnica'
      },
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Cerrar y Bloquear',
      cancelButtonText: 'Cancelar',
      preConfirm: (text) => {
        if (!text || text.length < 10) {
          Swal.showValidationMessage('Debe ingresar una justificación de al menos 10 caracteres.');
          return false; // Evita que se cierre el modal
        }
        return text;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // result.value contiene el texto escrito en el textarea
        this.executeHardGate(result.value);
      }
    });
  }

  /**
   * Ejecuta la llamada final al backend para aplicar el candado
   */
  private executeHardGate(justificationText: string): void {
    this.isSubmitting.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);
    // Ahora enviamos justificationText en lugar de this.justification()
    this.estimationService.closePlanningPhase(this.requirementId(), justificationText).subscribe({
      next: (response) => {
        Swal.fire('¡Fase Cerrada!', 'El requerimiento está ahora inmutable y en ejecución (ATF).', 'success');
        this.estimationForm.disable(); // Aplicamos el bloqueo visual total
        this.isSubmitting.set(false);
        this.successMessage.set(null); 
        this.hasSavedData.set(false); // Ocultamos botones si es necesario
        console.log('Respuesta del backend al cerrar fase:', response); // Log para debugging
      },
      error: (err) => {
        Swal.fire('Error', err.error?.message || 'No se pudo cerrar la fase.', 'error');
        this.isSubmitting.set(false);
      }
    });
  }
  /**
   * Helper para construir el payload reactivando los campos disabled
   */
  private getFormattedPayload(): EstimationPayload {
    const rawData = this.estimationForm.getRawValue();
    return {
      requirement_id: this.requirementId(),
      phases: rawData.phases
    };
  }
  // Función para volver al dashboard 
  goBackToDashboard(): void {
    this.router.navigate(['/dashboard']);
  }
}