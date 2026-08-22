import { Component, OnInit, inject, input, output, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import Swal from 'sweetalert2';

// Importaciones adaptadas a la nueva arquitectura
import { WorkflowPhaseService } from '../../data-access/services/workflow-phase.service';
import { NotificationService } from '../../data-access/services/notification.services';
import { PhaseConfig } from '../../data-access/models/phase-config.interface';
import { 
  WorkflowRecord, 
  WorkflowRegisterPayload, 
  WorkflowRegistersResponse, 
} from '../../data-access/models/workflow-phase.models';

@Component({
  selector: 'lib-phase-registers-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './phase-registers-modal.component.html',
  styleUrls: ['./phase-registers-modal.component.scss']
})
export class PhaseRegistersModalComponent implements OnInit {
  
  // =========================================================================
  // 1. INPUTS & OUTPUTS REQUERIDOS (Conectados al HTML Padre)
  // =========================================================================
  requirementId = input.required<string>();
  
  // Inputs polimórficos
  parentId = input.required<string>();
  parentName = input.required<string>();
  parentStatus = input.required<string>();
  
  config = input.required<PhaseConfig>();
  rrti = input.required<string>();

  closeModal = output<void>();

  // =========================================================================
  // 2. SERVICIOS (Inyección de Dependencias)
  // =========================================================================
  private readonly fb = inject(FormBuilder);
  private readonly phaseService = inject(WorkflowPhaseService);
  private readonly notificationService = inject(NotificationService);

  // =========================================================================
  // 3. ESTADOS REACTIVOS (Signals)
  // =========================================================================
  registerForm!: FormGroup;
  records = signal<WorkflowRecord[]>([]);
  isSaving = signal<boolean>(false);
  isLoading = signal<boolean>(true);
  editingRecordId = signal<string | null>(null);

  // =========================================================================
  // 4. COMPUTADOS POLIMÓRFICOS (Computed)
  // =========================================================================
  
  // Fase COE
  isCoePhase = computed(() => this.config().phaseCode === 'COE');

  // Sustantivos dinámicos según la fase
  parentLabel = computed(() => this.isCoePhase() ? 'Entregable' : 'Rol');
  recordName = computed(() => this.isCoePhase() ? 'actividad' : 'registro');
  recordNamePlural = computed(() => this.isCoePhase() ? 'actividades' : 'registros');
  
  modalTitle = computed(() => `Bitácora de ${this.config().phaseName}`);

  // =========================================================================
  // 5. CICLO DE VIDA E INICIALIZACIÓN
  // =========================================================================

  ngOnInit(): void {
    this.initForm();
    this.loadRecords();
  }

  // Inicialización estricta del formulario
  private initForm(): void {
    this.registerForm = this.fb.group({
      title: ['', [Validators.required, Validators.maxLength(255)]],
      date: [new Date().toISOString().substring(0, 10), Validators.required],
      description: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(500)]]
    });
  }
  
  // =========================================================================
  // 6. GESTIÓN DE DATOS Y TRANSACCIONES HTTP
  // =========================================================================

  loadRecords(): void {
    this.isLoading.set(true);
    
    // Inyección del config completo y uso del nuevo método getChildRegisters
    this.phaseService.getChildRegisters(this.parentId(), this.config()).subscribe({
      next: (response: WorkflowRegistersResponse) => {
        this.records.set(response.records || []);
        this.isLoading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.notificationService.showError('Error', 'No se pudo cargar el historial de la bitácora.');
        this.isLoading.set(false);
        console.error('Fallo al cargar registros:', err.message);
      }
    });
  }

  /**
   * Acción del botón guardar (Maneja Creación y Actualización)
   */
  onSubmit(): void {
    if (this.registerForm.invalid) {
      this.registerForm.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    const payload: WorkflowRegisterPayload = this.registerForm.value;
    const currentEditId = this.editingRecordId();
    const noun = this.recordName();

    if (currentEditId) {
      // ----------------------------------------------------
      // MODO ACTUALIZACIÓN (PUT)
      // ----------------------------------------------------
      this.phaseService.updateChildRegister(currentEditId, this.parentId(), this.config(), payload).subscribe({
        next: (updatedRecord: WorkflowRecord) => {
          this.records.update(current => 
            current.map(r => r.id === currentEditId ? updatedRecord : r)
          );
          this.resetForm();
          this.isSaving.set(false);
          this.notificationService.toastSuccess(`${noun.charAt(0).toUpperCase() + noun.slice(1)} actualizado correctamente.`);
          
          // 🟢 REACTIVIDAD: Avisar al Dashboard que los porcentajes pudieron haber cambiado
          this.phaseService.refreshDashboard$.next();
        },
        error: (err: HttpErrorResponse) => this.handleError(err, `No se pudo actualizar el ${noun}.`)
      });

    } else {
      // ----------------------------------------------------
      // MODO CREACIÓN (POST)
      // ----------------------------------------------------
      this.phaseService.storeChildRegister(this.requirementId(), this.parentId(), this.config(), payload).subscribe({
        next: (newRecord: WorkflowRecord) => {
          this.records.update(current => [newRecord, ...current]);
          this.resetForm();
          this.isSaving.set(false);
          this.notificationService.toastSuccess(`${noun.charAt(0).toUpperCase() + noun.slice(1)} guardado exitosamente.`);
          
          // 🟢 REACTIVIDAD: Avisar al Dashboard para reflejar el estado PI-I y la nueva barra de progreso
          this.phaseService.refreshDashboard$.next();
        },
        error: (err: HttpErrorResponse) => this.handleError(err, `No se pudo crear el ${noun}.`)
      });
    }
  }

  /**
   * Elimina un registro de la bitácora (Físico para DT, Lógico para COR/COE/PI)
   */
  async deleteRecord(regId: string): Promise<void> {
    const regToDelete = this.records().find(r => r.id === regId);
    if (!regToDelete) return;
    
    const noun = this.recordName();
    const isConfirmed = await this.notificationService.confirm(
      `Eliminar ${noun.charAt(0).toUpperCase() + noun.slice(1)}`,
      `¿Estás seguro que deseas eliminar "${regToDelete.title}"? Esta acción es irreversible.`
    );

    if (isConfirmed) {
      this.phaseService.deleteChildRegister(regId, this.parentId(), this.config()).subscribe({
        next: () => {
          this.records.update(current => current.filter(r => r.id !== regId));
          this.notificationService.toastSuccess(`${noun.charAt(0).toUpperCase() + noun.slice(1)} eliminado exitosamente.`);
          
          if (this.editingRecordId() === regId) this.resetForm();

          // 🟢 REACTIVIDAD: Avisar al Dashboard ante cualquier cambio de estado
          this.phaseService.refreshDashboard$.next();
        },
        error: (err: HttpErrorResponse) => {
          this.notificationService.showError('Error', `No se pudo eliminar el ${noun}.`);
          console.error(err.message);
        }
      });
    }
  }

  // =========================================================================
  // 7. FUNCIONES DE UI Y MANEJO DE ESTADO
  // =========================================================================

  /**
   * Prepara el formulario para EDICIÓN
   */
  editRecord(reg: WorkflowRecord): void {
    this.editingRecordId.set(reg.id);
    this.registerForm.patchValue({
      title: reg.title,
      date: reg.date ? reg.date.substring(0, 10) : '',
      description: reg.description
    });
  }

  /**
   * Cancela la edición y limpia el formulario
   */
  resetForm(): void {
    this.editingRecordId.set(null);
    this.registerForm.reset({
      date: new Date().toISOString().substring(0, 10)
    });
  }

  /**
   * Muestra los detalles de un registro en modo Solo Lectura con diseño corporativo
   */
  viewRecord(reg: WorkflowRecord): void {
    const rawDate = reg.date ? new Date(reg.date) : new Date();
    // Ajuste de zona horaria local mediante toLocaleDateString
    const formattedDate = rawDate.toLocaleDateString('es-VE', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    });

    const phaseName = this.config().phaseName;
    const noun = this.recordName();

    Swal.fire({
      title: '',
      icon: undefined,
      html: `
        <div style="text-align: left; padding: 0.5rem;">
          <!-- Cabecera Personalizada -->
          <div style="display: flex; align-items: center; border-bottom: 2px solid #ea80fc; padding-bottom: 12px; margin-bottom: 20px;">
            <i class="fa-solid fa-book-journal-whills" style="font-size: 1.5rem; color: #d500f9; margin-right: 12px;"></i>
            <h5 style="margin: 0; font-weight: 700; color: #333; font-size: 1.25rem;">Detalle de la ${noun.charAt(0).toUpperCase() + noun.slice(1)} - ${phaseName}</h5>
          </div>
          
          <!-- Cuerpo de Datos -->
          <div style="margin-bottom: 16px;">
            <span style="font-size: 0.75rem; color: #6c757d; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">Título </span>
            <p style="margin: 4px 0 0 0; font-size: 1rem; color: #212529; font-weight: 500;">${reg.title}</p>
          </div>
          
          <div style="margin-bottom: 20px;">
            <span style="font-size: 0.75rem; color: #6c757d; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">Fecha de Creación</span>
            <p style="margin: 4px 0 0 0; font-size: 1rem; color: #212529;"><i class="fa-regular fa-calendar text-muted me-2"></i>${formattedDate}</p>
          </div>
          
          <!-- Caja de Descripción -->
          <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 16px;">
            <span style="font-size: 0.75rem; color: #6c757d; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; display: block; margin-bottom: 8px;">Descripción Detallada</span>
            <div style="font-size: 0.95rem; color: #495057; white-space: pre-wrap; line-height: 1.6; text-align: justify;">${reg.description}</div>
          </div>
        </div>
      `,
      width: '600px',
      padding: '1rem',
      showCloseButton: true,
      buttonsStyling: false,
      customClass: {
        popup: 'rounded-4 shadow-lg border-0',
        htmlContainer: 'p-0 m-0'
      },
      didOpen: () => {
        const confirmBtn = Swal.getConfirmButton();
        if (confirmBtn) {
          confirmBtn.textContent = 'Cerrar Vista';
          confirmBtn.style.background = 'linear-gradient(90deg, #aa00ff 0%, #d500f9 100%)';
          confirmBtn.style.color = 'white';
          confirmBtn.style.border = 'none';
          confirmBtn.style.borderRadius = '0.375rem';
          confirmBtn.style.padding = '0.5rem 1.5rem';
          confirmBtn.style.fontWeight = '600';
          confirmBtn.style.boxShadow = '0 4px 12px rgba(213, 0, 249, 0.3)';
          
          confirmBtn.onmouseover = () => {
            confirmBtn.style.transform = 'translateY(-1px)';
            confirmBtn.style.boxShadow = '0 6px 15px rgba(213, 0, 249, 0.4)';
          };
          confirmBtn.onmouseout = () => {
            confirmBtn.style.transform = 'none';
            confirmBtn.style.boxShadow = '0 4px 12px rgba(213, 0, 249, 0.3)';
          };
        }
      }
    });
  }

  /**
   * Centraliza el manejo de errores HTTP y FormRequests (Laravel)
   */
  private handleError(err: HttpErrorResponse, fallbackMessage: string): void {
    this.isSaving.set(false);
    if (err.status === 422 && err.error.errors) {
      const firstError = Object.values(err.error.errors)[0] as string[];
      this.notificationService.showError('Validación', firstError[0]);
    } else {
      this.notificationService.showError('Error', err.error?.message || fallbackMessage);
    }
    console.error(fallbackMessage, err.message);
  }

}