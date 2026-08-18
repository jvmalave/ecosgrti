import { Component, OnInit, inject, input, output, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { WorkflowPhaseService } from '../../data-access/services/workflow-phase.service';
import { NotificationService } from '../../data-access/services/notification.services';
import { PhaseConfig } from '../../data-access/models/phase-config.interface';
import { WorkflowPhaseItem } from '../../data-access/models/workflow-phase.models'; 
import { PhaseRegistersModalComponent } from '../phase-registers-modal/phase-registers-modal.component';
import { PiTestUsersModalComponent } from '../pi-test-users-modal/pi-test-users-modal.component';
import { PiApprovalModalComponent } from '../pi-approval-modal/pi-approval-modal.component';

@Component({
  selector: 'lib-phase-roles-modal',
  standalone: true,
  imports: [
    CommonModule, 
    PhaseRegistersModalComponent, 
    PiTestUsersModalComponent,
    PiApprovalModalComponent
  ],
  templateUrl: './phase-roles-modal.component.html',
  styleUrls: ['./phase-roles-modal.component.scss']
})
export class PhaseRolesModalComponent implements OnInit {
  private readonly phaseService = inject(WorkflowPhaseService);
  private readonly notificationService = inject(NotificationService);

  // =========================================================================
  // 1. INPUTS & OUTPUTS
  // =========================================================================
  requirementId = input.required<string>();
  config = input.required<PhaseConfig>();
  rrti = input.required<string>();
  frozenPhases = input.required<string[]>();

  consultantName = input<string>('Consultor Funcional Asignado');
  functionalUnit = input<string>('Unidad Solicitante');

  closeModal = output<void>();

  // =========================================================================
  // 2. ESTADO REACTIVO (Signals)
  // =========================================================================
  // Data principal
  phaseItems = signal<WorkflowPhaseItem[]>([]);
  isLoading = signal<boolean>(true);
  errorMessage = signal<string | null>(null);
  searchQuery = signal<string>('');
  isLocallyClosed = signal<boolean>(false);

  // Controladores de Modales Secundarios
  selectedItemForRegisters = signal<WorkflowPhaseItem | null>(null);
  selectedItemForTestUsers = signal<WorkflowPhaseItem | null>(null);
  selectedItemForApproval = signal<WorkflowPhaseItem | null>(null);

  // =========================================================================
  // 3. ESTADOS COMPUTADOS (Computed)
  // =========================================================================
  modalTitle = computed(() => this.config().modalTitle);
  
  // Evaluación polimórfica para nomenclaturas
  isDeliverablesPhase = computed(() => ['COE', 'CEE'].includes(this.config().phaseCode));
  itemName = computed(() => this.isDeliverablesPhase() ? 'Entregable' : 'Rol');
  itemNamePlural = computed(() => this.isDeliverablesPhase() ? 'Entregables' : 'Roles');

  // Inmutabilidad Arquitectónica: Evaluamos la jerarquía del backend
  isPhaseClosed = computed(() => {
    const frozen = this.frozenPhases() || [];
    // Se considera cerrada si viene del backend (frozenPhases) o si se acaba de cerrar en esta sesión (isLocallyClosed)
    return frozen.includes(this.config().phaseCode) || this.isLocallyClosed();
  });

  isPhaseActive = computed(() => !this.isPhaseClosed());

  // Búsqueda polimórfica 
  filteredItems = computed(() => {
    const query = (this.searchQuery() || '').toLowerCase();
    return this.phaseItems().filter(item => {
      const name = item.name || item.master_deliverable?.name || '';
      return name.toLowerCase().includes(query);
    });
  });

  // Segmentación de listas
  activeItems = computed(() => this.filteredItems().filter(item => item.status === 'IN_PROGRESS'));
  closedItems = computed(() => this.filteredItems().filter(item => item.status === 'CLOSED'));

  isPhaseCloseEnabled = computed(() => {
    const total = this.phaseItems().length;
    const closed = this.closedItems().length;
    return total > 0 && total === closed;
  });

  // =========================================================================
  // 4. CICLO DE VIDA Y CARGA DE DATOS
  // =========================================================================
  
  ngOnInit(): void {
    this.loadItems();
  }

  loadItems(): void {
    this.isLoading.set(true);
    
    this.phaseService.initializePhaseComponents(this.requirementId(), this.config())
      .subscribe({
        next: (response) => {
          this.phaseItems.set(response.roles_list);
          this.isLoading.set(false);
        },
        error: (err: HttpErrorResponse) => {
          this.notificationService.showError('Error', `No se pudieron cargar los datos de la fase.`);
          this.isLoading.set(false);
          console.error('Detalle del error HTTP:', err.message, err.error);
        }
      });
  }

  updateSearch(event: Event): void {
    const inputElement = event.target as HTMLInputElement;
    this.searchQuery.set(inputElement.value);
  }

  // =========================================================================
  // 5. CONTROLADORES DE MODALES SECUNDARIOS
  // =========================================================================

  // -- Bitácora --
  openRegisters(item: WorkflowPhaseItem): void {
    this.selectedItemForRegisters.set(item);
  }

  closeRegistersAndRefresh(): void {
    this.selectedItemForRegisters.set(null); 
    this.loadItems(); // Recarga para actualizar conteos
  }

  // -- Usuarios de Prueba (CU-042) --
  openTestUsers(item: WorkflowPhaseItem): void {
    this.selectedItemForTestUsers.set(item);
  }

  closeTestUsersModal(): void {
    this.selectedItemForTestUsers.set(null);
  }

  // -- Aprobación Funcional (CU-044) --
  openFunctionalApproval(item: WorkflowPhaseItem): void {
    this.selectedItemForApproval.set(item);
  }

  closeFunctionalApprovalModal(): void {
    this.selectedItemForApproval.set(null);
    this.loadItems(); // Vital recargar para obtener el 'is_approved' actualizado
  }

  // =========================================================================
  // 6. LÓGICA DE NEGOCIO: GATEKEEPER DE PRUEBAS INTEGRALES (PI)
  // =========================================================================

  /**
   * RN-PI-9: Evalúa si un rol en PI cumple los requisitos para cerrarse.
   * Exige al menos 1 registro en bitácora y el flag de aprobación funcional.
   */
  canClosePiRole(item: WorkflowPhaseItem): boolean {
    if (this.config().phaseCode !== 'PI') return true; 
    
    const hasLogs = (item.registers_count ?? 0) > 0;
    const isApproved = item.is_approved === true;
    
    return hasLogs && isApproved;
  }

  /**
   * RN-PI-9: Feedback dinámico del motivo de bloqueo en el Tooltip.
   */
  getClosePiRoleTooltip(item: WorkflowPhaseItem): string {
    if (this.config().phaseCode !== 'PI') return 'Cerrar ' + this.itemName();
    
    const hasLogs = (item.registers_count ?? 0) > 0;
    const isApproved = item.is_approved === true;

    if (!hasLogs) return 'Bloqueado: Debe registrar al menos un hallazgo en la bitácora.';
    if (!isApproved) return 'Bloqueado: Requiere la Aprobación Funcional con documento soporte.';
    
    return 'Requisitos completados. Cerrar Rol.';
  }



  // =========================================================================
  // 7. TRANSACCIONES Y MUTACIONES HTTP
  // =========================================================================

  async closeItem(item: WorkflowPhaseItem): Promise<void> {
    const displayName = item.name || item.master_deliverable?.name || 'Seleccionado';
    const noun = this.itemName();

    const isConfirmed = await this.notificationService.confirm(
      `Cerrar ${noun}`,
      `¿Estás seguro que deseas cerrar el ${noun} "${displayName}"? Esta acción bloqueará la adición de nuevas bitácoras.`
    );

    if (isConfirmed) {
      this.phaseService.changeComponentStatus(item.id, this.config(), 'CLOSED')
        .subscribe({
          next: () => {
            // Mueve reactivamente el item a "Roles Cerrados" en el estado local
            this.phaseItems.update(items => items.map(i => i.id === item.id ? { ...i, status: 'CLOSED' } : i));
            
            this.notificationService.toastSuccess(`${noun.charAt(0).toUpperCase() + noun.slice(1)} cerrado exitosamente.`);
            
            // Refrescamos el dashboard general
            this.phaseService.refreshDashboard$.next();
          },
          error: (err: HttpErrorResponse) => {
            console.error('Detalle del error HTTP:', err.message, err.error);
            
            // Manejo dinámico del error
            let errorMessage = `No se pudo procesar la acción para el ${noun}.`;
            
            if (err.status >= 500) {
                // Si el servidor explota (Error 500+), mostramos un mensaje genérico y elegante
                errorMessage = 'Ocurrió un error interno en el servidor. Por favor, intente nuevamente o contacte a soporte técnico.';
            } else if (err.error?.message) {
                // Si es un error de regla de negocio (422, 403, 404), mostramos el mensaje de Laravel
                errorMessage = err.error.message;
            }
            
            if (err.status === 422 || err.status === 403) {
                this.notificationService.showWarning('Acción Denegada', errorMessage);
            } else {
                this.notificationService.showError('Error Transaccional', errorMessage);
            }
          }
        });
    }
  }



  async reopenItem(item: WorkflowPhaseItem): Promise<void> {
    const displayName = item.name || item.master_deliverable?.name || 'Seleccionado';
    const noun = this.itemName();

    const isConfirmed = await this.notificationService.confirm(
      `Reabrir ${noun}`,
      `¿Estás seguro que deseas reabrir el ${noun} "${displayName}"? Esto permitirá agregar nuevos registros.`
    );

    if (isConfirmed) {
      this.phaseService.changeComponentStatus(item.id, this.config(), 'IN_PROGRESS')
        .subscribe({
          next: () => {
            this.phaseItems.update(items => items.map(i => i.id === item.id ? { ...i, status: 'IN_PROGRESS' } : i));
            this.notificationService.toastSuccess(`${noun.charAt(0).toUpperCase() + noun.slice(1)} reabierto exitosamente.`);
            this.phaseService.refreshDashboard$.next();
          },
          error: (err: HttpErrorResponse) => {
            this.notificationService.showError(
                'Reapertura Denegada', 
                err.error?.message || `No se pudo reabrir el ${noun}. Verifica que la fase global siga activa.`
            );
          }
        });
    }
  }

async closeGlobalPhase(): Promise<void> {
    // Extrae el nombre para evitar llamar a this.config().phaseName múltiples veces
    const phaseName = this.config().phaseName;

    const isConfirmed = await this.notificationService.confirm(
      `Cerrar Fase de ${phaseName}`,
      `Todos los elementos han sido cerrados. ¿Deseas dar por finalizada la fase de ${phaseName}?`
    );

    // Patrón Early Return: Si el usuario cancela, salimos de inmediato.
    if (!isConfirmed) return;

    this.isLoading.set(true);

    this.phaseService.closePhase(this.requirementId(), this.config()).subscribe({
      next: () => {
        // Apaga el indicador de carga
        this.isLoading.set(false);

        // BLOQUEO VISUAL INSTANTÁNEO EN EL MODAL (Reactividad Local)
        this.isLocallyClosed.set(true);

        // Notificación de éxito
        this.notificationService.toastSuccess(`Fase de ${phaseName} finalizada con éxito.`);

        // DISPARO AL DASHBOARD (Reactividad Global)
        this.phaseService.refreshDashboard$.next();
      },
      error: (err: HttpErrorResponse) => {
        console.error(`Fallo en closeGlobalPhase (${phaseName}):`, err.message, err.error);
        this.isLoading.set(false);
        
        // PROTECCIÓN UX: Manejo dinámico del error
        let errorMessage = `Hubo un problema al intentar cerrar la fase global de ${phaseName}.`;
        
        if (err.status >= 500) {
            // Enmascarar errores de servidor (Ej: Fallos de base de datos)
            errorMessage = 'Ocurrió un error interno en el servidor al intentar cerrar la fase. Por favor, contacte a soporte.';
        } else if (err.error?.message) {
            // Rescatamos el mensaje específico de reglas de negocio del Backend
            errorMessage = err.error.message;
        }
        
        // Mostramos el tipo de alerta adecuado según el código HTTP
        if (err.status === 422 || err.status === 403) {
            this.notificationService.showWarning('Cierre Denegado', errorMessage);
        } else {
            this.notificationService.showError('Error de Sistema', errorMessage);
        }
      }
    });
  }

  /**
   * Descarga y muestra el acta de aprobación funcional (PDF)
   */
  viewApprovalDocument(item: WorkflowPhaseItem): void {
    this.phaseService.downloadFunctionalApproval(this.requirementId(), item.id).subscribe({
      next: (blob: Blob) => {
        // Crea una URL temporal para el Blob en la memoria del navegador
        const fileURL = URL.createObjectURL(blob);
        // Abre el PDF en una nueva pestaña
        window.open(fileURL, '_blank');
      },
      error: (err: HttpErrorResponse) => {
        const errorMessage = err.error?.message || 'El documento no se encuentra disponible.';
        this.notificationService.showError('Error', errorMessage);
      }
    });
  }
}