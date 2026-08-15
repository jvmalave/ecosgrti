import { Component, OnInit, inject, input, output, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { WorkflowPhaseService } from '../../data-access/services/workflow-phase.service';
import { NotificationService } from '../../data-access/services/notification.services';
import { PhaseConfig } from '../../data-access/models/phase-config.interface';
import { WorkflowPhaseItem } from '../../data-access/models/workflow-phase.models'; 
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
  private readonly notificationService = inject(NotificationService);

  // =========================================================================
  // INPUTS & OUTPUTS
  // =========================================================================
  requirementId = input.required<string>();
  config = input.required<PhaseConfig>();
  rrti = input.required<string>();
  
  // 🟢 AHORA: Recibimos el arreglo de fases congeladas emitido por el backend
  frozenPhases = input.required<string[]>();

  closeModal = output<void>();

  // =========================================================================
  // ESTADO REACTIVO (Signals)
  // =========================================================================
  phaseItems = signal<WorkflowPhaseItem[]>([]);
  isLoading = signal<boolean>(true);
  errorMessage = signal<string | null>(null);
  
  searchQuery = signal<string>('');
  selectedItemForRegisters = signal<WorkflowPhaseItem | null>(null);

  // =========================================================================
  // COMPUTADOS (Computed)
  // =========================================================================
  modalTitle = computed(() => this.config().modalTitle);
  // 🟢 Evaluación polimórfica para nomenclaturas (aplica para COE y CEE)
  isDeliverablesPhase = computed(() => ['COE', 'CEE'].includes(this.config().phaseCode));
  itemName = computed(() => this.isDeliverablesPhase() ? 'Entregable' : 'Rol');
  itemNamePlural = computed(() => this.isDeliverablesPhase() ? 'Entregables' : 'Roles');

  // 🟢 INMUTABILIDAD ARQUITECTÓNICA: Evaluamos la jerarquía del backend
  isPhaseClosed = computed(() => {
    const frozen = this.frozenPhases() || [];
    // Si la fase actual ('DT', 'COR', 'PI') existe en el arreglo de congeladas, está cerrada.
    return frozen.includes(this.config().phaseCode);
  });

  isPhaseActive = computed(() => {
    return !this.isPhaseClosed();
  });

  // Búsqueda polimórfica 
  filteredItems = computed(() => {
    const query = (this.searchQuery() || '').toLowerCase();
    return this.phaseItems().filter(item => {
      const name = item.name || item.master_deliverable?.name || '';
      return name.toLowerCase().includes(query);
    });
  });

  activeItems = computed(() => this.filteredItems().filter(item => item.status === 'IN_PROGRESS'));
  closedItems = computed(() => this.filteredItems().filter(item => item.status === 'CLOSED'));

  isPhaseCloseEnabled = computed(() => {
    const total = this.phaseItems().length;
    const closed = this.closedItems().length;
    return total > 0 && total === closed;
  });

  // =========================================================================
  // CICLO DE VIDA Y MÉTODOS
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

  openRegisters(item: WorkflowPhaseItem): void {
    this.selectedItemForRegisters.set(item);
  }

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
            this.phaseItems.update(items => items.map(i => i.id === item.id ? { ...i, status: 'CLOSED' } : i));
            this.notificationService.toastSuccess(`${noun.charAt(0).toUpperCase() + noun.slice(1)} cerrado exitosamente.`);
            this.phaseService.refreshDashboard$.next();
          },
          error: (err: HttpErrorResponse) => {
            console.error('Detalle del error HTTP:', err.message, err.error);
            if (err.status === 422) {
                this.notificationService.showWarning(
                  'Acción Denegada', 
                  `No se puede cerrar el ${noun} porque requiere al menos un registro en su bitácora.`
                );
            } else {
                this.notificationService.showError('Error Transaccional', `No se pudo procesar el cierre del ${noun}.`);
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
      const isConfirmed = await this.notificationService.confirm(
          `Cerrar Fase de ${this.config().phaseName}`,
          `Todos los elementos han sido cerrados. ¿Deseas dar por finalizada la fase de ${this.config().phaseName}?`
      );

      if(isConfirmed) {
          this.isLoading.set(true);
          this.phaseService.closePhase(this.requirementId(), this.config())
            .subscribe({
              next: () => {
                this.isLoading.set(false);
                this.notificationService.toastSuccess(`Fase de ${this.config().phaseName} finalizada.`);
                this.phaseService.refreshDashboard$.next();
              },
              error: (err: HttpErrorResponse) => {
                console.error('Fallo en closeGlobalPhase:', err.message);
                this.notificationService.showError('Error de Sistema', 'Hubo un problema al intentar cerrar la fase global.');
                this.isLoading.set(false);
              }
            });
      }
  }

  closeRegistersAndRefresh(): void {
    this.selectedItemForRegisters.set(null); 
    this.loadItems(); 
  }
}