import { Component, OnInit, inject, input, output, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { WorkflowPhaseService } from '../../data-access/services/workflow-phase.service';
import { NotificationService } from '../../data-access/services/notification.services'; // Añadido
import { PhaseConfig } from '../../data-access/models/phase-config.interface';
import { WorkflowRole } from '../../data-access/models/workflow-phase.models';
import { PhaseRegistersModalComponent } from '../phase-registers-modal/phase-registers-modal.component';

@Component({
  selector: 'lib-phase-roles-modal',
  standalone: true,
  imports: [CommonModule, PhaseRegistersModalComponent],
  templateUrl: './phase-roles-modal.component.html',
  styleUrls: ['./phase-roles-modal.component.scss']
})
export class PhaseRolesModalComponent implements OnInit {
  private readonly phaseService = inject(WorkflowPhaseService);
  private readonly notificationService = inject(NotificationService); // Añadido

  // =========================================================================
  // INPUTS & OUTPUTS
  // =========================================================================
  requirementId = input.required<string>();
  config = input.required<PhaseConfig>();
  rrti = input.required<string>();
  reqStatus = input.required<string>();

  closeModal = output<void>();

  // =========================================================================
  // ESTADO REACTIVO (Signals)
  // =========================================================================
  roles = signal<WorkflowRole[]>([]);
  isLoading = signal<boolean>(true);
  errorMessage = signal<string | null>(null);
  
  searchQuery = signal<string>('');
  selectedRoleForRegisters = signal<WorkflowRole | null>(null);

  // =========================================================================
  // COMPUTADOS (La magia de la UI)
  // =========================================================================
  modalTitle = computed(() => this.config().modalTitle);
  apiEndpoint = computed(() => this.config().apiEndpoint);

  // Lógica polimórfica para saber si la fase está abierta y permite edición
  isPhaseActive = computed(() => {
    const status = this.reqStatus();
    const code = this.config().phaseCode;
    
    // Hard-Gates según la fase actual
    if (code === 'DT') return ['ATF-C', 'DT-I'].includes(status);
    if (code === 'COR') return ['DT-C', 'COR-I'].includes(status);
    if (code === 'COE') return ['COR-C', 'COE-I'].includes(status);
    
    return false;
  });

  // Filtro de búsqueda
  filteredRoles = computed(() => {
    const query = (this.searchQuery() || '').toLowerCase();
    return this.roles().filter(role => {
      const roleName = role.name || '';
      return roleName.toLowerCase().includes(query);
    });
  });

  // Separación por estados
  activeRoles = computed(() => this.filteredRoles().filter(r => r.status === 'IN_PROGRESS'));
  closedRoles = computed(() => this.filteredRoles().filter(r => r.status === 'CLOSED'));

  // Validación para el botón final de "Cerrar Fase"
  isPhaseCloseEnabled = computed(() => {
    const total = this.roles().length;
    const closed = this.closedRoles().length;
    // Habilitado solo si hay roles y TODOS están cerrados
    return total > 0 && total === closed;
  });

  // =========================================================================
  // CICLO DE VIDA Y MÉTODOS
  // =========================================================================
  
  ngOnInit(): void {
    this.loadRoles();
  }

  loadRoles(): void {
    this.isLoading.set(true);
    this.phaseService.initializeRoles(this.requirementId(), this.apiEndpoint())
      .subscribe({
        next: (response) => {
          this.roles.set(response.roles_list);
          this.isLoading.set(false);
        },
        error: (err: HttpErrorResponse) => {
          this.notificationService.showError('Error', 'No se pudieron cargar los roles.'); // Ajustado
          this.isLoading.set(false);
          console.error('Detalle del error HTTP:', err.message, err.error);
        }
      });
  }

  updateSearch(event: Event): void {
    const inputElement = event.target as HTMLInputElement;
    this.searchQuery.set(inputElement.value);
  }

  openRegisters(role: WorkflowRole): void {
    this.selectedRoleForRegisters.set(role);
  }

  /**
   * Cierra un rol técnico individual previa confirmación
   */
  async closeRole(role: WorkflowRole): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      'Cerrar Rol Técnico',
      `¿Estás seguro que deseas cerrar el rol "${role.name}"? Esta acción bloqueará la adición de nuevas bitácoras.`
    );

    if (isConfirmed) {
      this.phaseService.changeRoleStatus(role.id, this.apiEndpoint(), 'CLOSED')
        .subscribe({
          next: () => {
            this.roles.update(roles => roles.map(r => r.id === role.id ? { ...r, status: 'CLOSED' } : r));
            this.notificationService.toastSuccess('Rol técnico cerrado exitosamente.');
          },
          error: (err: HttpErrorResponse) => {
             // Manejo de reglas de negocio bloqueadas por Backend (pest)
            if (err.status === 422) {
                this.notificationService.showWarning(
                  'Acción Denegada', 
                  'No se puede cerrar el rol porque requiere al menos un registro técnico en la bitácora.'
                );
            } else {
                 this.notificationService.showError('Error Transaccional', 'No se pudo procesar el cierre del rol.');
            }
            console.error('Fallo en closeRole:', err.message);
          }
        });
    }
  }

  /**
   * Reabre un rol técnico cerrado previa confirmación
   */
  async reopenRole(role: WorkflowRole): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      'Reabrir Rol Técnico',
      `¿Estás seguro que deseas reabrir el rol "${role.name}"? Esto permitirá agregar nuevos registros en la bitácora a este rol.`
    );

    if (isConfirmed) {
      this.phaseService.changeRoleStatus(role.id, this.apiEndpoint(), 'IN_PROGRESS')
        .subscribe({
          next: () => {
            this.roles.update(roles => roles.map(r => r.id === role.id ? { ...r, status: 'IN_PROGRESS' } : r));
            this.notificationService.toastSuccess('Rol técnico reabierto exitosamente.');
          },
          error: (err: HttpErrorResponse) => {
            this.notificationService.showError(
                'Reapertura Denegada', 
                err.error?.message || 'No se pudo reabrir el rol. Verifica que la fase global siga activa.'
            );
            console.error('Fallo en reopenRole:', err.message);
          }
        });
    }
  }

  /**
   * Cierra la fase completa
   */
  async closeGlobalPhase(): Promise<void> {
      const isConfirmed = await this.notificationService.confirm(
          `Cerrar Fase de ${this.config().phaseName}`,
          `Todos los roles han sido cerrados. ¿Deseas dar por finalizada la fase de ${this.config().phaseName} para este requerimiento?`
      );

      if(isConfirmed) {
          this.isLoading.set(true);
          this.phaseService.closePhase(this.requirementId(), this.apiEndpoint())
            .subscribe({
              next: () => {
                this.isLoading.set(false);
                this.notificationService.toastSuccess(`Fase de ${this.config().phaseName} finalizada.`);
                this.closeModal.emit();
                // Avisar al dashboard que debe refrescar los indicadores
                this.phaseService.refreshDashboard$.next();
              },
              error: (err: HttpErrorResponse) => {
                this.notificationService.showError('Error de Sistema', 'Hubo un problema al intentar cerrar la fase global.');
                console.error('Error al cerrar la fase', err);
                this.isLoading.set(false);
              }
            });
      }
  }
}