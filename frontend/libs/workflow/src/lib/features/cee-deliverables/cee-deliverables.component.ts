import { Component, OnInit, inject, input, signal, computed, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CeeDeliverablesStore } from '../../data-access/store/cee-deliverables.store';
import { CeeApiService } from '../../data-access/services/cee-api.service';
import { CeeDeliverable } from '../../data-access/models/cee-workflow.model';
import { NotificationService } from '../../data-access/services/notification.services';
import { CeeTicketFormComponent } from './components/cee-ticket-form/cee-ticket-form.component';
import { CeeResultFormComponent } from './components/cee-result-form/cee-result-form.component';
import { HttpErrorResponse } from '@angular/common/http';



// Interfaz auxiliar local para agrupar los entregables por ticket
export interface TicketDeliverableGroup {
  ticketId: string;
  ticketNumber: string;
  deliverablesCount: number;
  deliverables: CeeDeliverable[];
}

// IMPORTANTE: Deberás crear/ajustar estos dos componentes hijos en el siguiente paso



@Component({
  selector: 'lib-cee-deliverables',
  standalone: true,
  imports: [
    CommonModule, 
    FormsModule,
    CeeTicketFormComponent,
    CeeResultFormComponent
  ], 
  templateUrl: './cee-deliverables.component.html',
  styleUrls: ['./cee-deliverables.component.scss'] // O el que estés utilizando
})
export class CeeDeliverablesComponent implements OnInit {
  // ==========================================
  // INYECCIÓN DE DEPENDENCIAS
  // ==========================================
  public readonly store = inject(CeeDeliverablesStore);
  private readonly ceeApiService = inject(CeeApiService);
  private readonly notificationService = inject(NotificationService);

  // ==========================================
  // INPUTS Y OUTPUTS
  // ==========================================
  public requirementId = input.required<string>();
  public rrti = input.required<string>();
  public reqPhase = input<string>('CEE-I');
  
  public phaseClosed = output<{req_id: string, phase_actual: string, progreso_global: number}>();

  // ==========================================
  // SIGNALS Y COMPUTED PARA EL MODAL DE TICKETS
  // ==========================================
  public showTicketModal = signal<boolean>(false);
  public deliverablesToProcess = signal<string[]>([]);
  public selectedDeliverablesData = computed(() => {
    return this.store.filteredPending().filter(d => this.deliverablesToProcess().includes(d.id));
  });


  public isSubmitting = signal<boolean>(false);

  // ==========================================
  // COMPUTED PARA TABLAS AGRUPADAS POR TICKET
  // ==========================================
  public ticketsInProgress = computed<TicketDeliverableGroup[]>(() => {
    const deliverables = this.store.deliverables().filter(d => d.status === 'IN_PROGRESS');
    let groups = this.groupDeliverablesByTicket(deliverables);

    const term = this.store.searchTerm().trim().toLowerCase();
    if (term) {
      groups = groups.filter(g => g.ticketNumber.toLowerCase().includes(term));
    }

    return groups;
  });

  public ticketsCertified = computed<TicketDeliverableGroup[]>(() => {
    const deliverables = this.store.deliverables().filter(d => d.status === 'CERTIFIED');
    let groups = this.groupDeliverablesByTicket(deliverables);

    const term = this.store.searchTerm().trim().toLowerCase();
    if (term) {
      groups = groups.filter(g => g.ticketNumber.toLowerCase().includes(term));
    }

    return groups;
  });

  // ==========================================
  // HARD GATE: QUÓRUM DE CIERRE GLOBAL
  // ==========================================
  
  public isPhaseClosed = computed<boolean>(() => {
    const phase = this.reqPhase();
    // Solo muestra el banner si el requerimiento está explícitamente en CEE-C o fases posteriores
    const closedPhases = ['CEE-C', 'PI-I', 'PI-C', 'PAP-I', 'PAP-C', 'AU', 'RF'];
    return closedPhases.includes(phase);
  });

  public canClosePhase = computed<boolean>(() => {
    const allDeliverables = this.store.deliverables();
    
    // Si no hay entregables, bloqueamos el cierre
    if (allDeliverables.length === 0) return false;

    // Evaluamos el estado absoluto ignorando el buscador
    const hasPending = allDeliverables.some(d => d.status === 'PENDING_CERTIFICATION');
    const hasInProgress = allDeliverables.some(d => d.status === 'IN_PROGRESS');
    
    return !hasPending && !hasInProgress && !this.isPhaseClosed();
  });

  // ==========================================
  // SIGNALS PARA EL MODAL DE RESULTADOS
  // ==========================================
  public showResultModal = signal<boolean>(false);
  public isResultViewMode = signal<boolean>(false);
  public activeTicketId = signal<string>('');
  public activeTicketNumber = signal<string>('');
  public activeDeliverables = signal<CeeDeliverable[]>([]);

  ngOnInit(): void {
    this.initializeWorkflow();
  }

  // ==========================================
  // METODO PARA INICIALIZACIÓN DE LA FASE
  // ==========================================
  private initializeWorkflow(): void {
    this.ceeApiService.initDeliverables(this.requirementId()).subscribe({
      next: (response) => this.store.setDeliverables(response.deliverables),
      error: (error) => console.error('Error inicializando la fase CEE:', error)
    });
  }

  // ==========================================
  // MÉTODOS PARA BUSQUEDA
  // ==========================================
  public onSearchTermChange(term: string): void {
    this.store.updateSearchTerm(term);
  }

  // ==========================================
  // MÉTODOS PARA SELECCIONAR ENTREGABLES
  // ==========================================
  public toggleDeliverableSelection(deliverableId: string, event: Event): void {
    const isChecked = (event.target as HTMLInputElement).checked;
    this.deliverablesToProcess.update(currentSelected => {
      if (isChecked) {
        return [...currentSelected, deliverableId];
      } else {
        return currentSelected.filter(id => id !== deliverableId);
      }
    });
  }

  // ==========================================
  // MÉTODOS PARA ABRIR EL MODAL DE TICKETS
  // ==========================================
  public openTicketModal(): void {
    if (this.deliverablesToProcess().length > 0) {
      this.showTicketModal.set(true);
    }
  }

  public onTicketSuccessfullyCreated(): void {
    this.showTicketModal.set(false);
    this.deliverablesToProcess.set([]);
    this.initializeWorkflow(); 
  }

  // ==========================================
  // MÉTODOS PARA ABRIR Y CERRAR EL DICTAMEN
  // ==========================================
  public openResultModal(ticketId: string | null, mode: 'create' | 'view' = 'create'): void {
    if (!ticketId) return;

    const allDeliverables = [
      ...this.store.filteredPending(), 
      ...this.store.filteredInProgress(), 
      ...this.store.filteredCertified()
    ];
    
    const deliverablesInTicket = allDeliverables.filter(d => d.ticket_id === ticketId);
    
    if (deliverablesInTicket.length > 0) {
      this.activeTicketId.set(ticketId);
      const realTicketNumber = deliverablesInTicket[0].ticket?.ticket_number || `ID-${ticketId.split('-')[0].toUpperCase()}`;
      
      this.activeTicketNumber.set(realTicketNumber); 
      this.activeDeliverables.set(deliverablesInTicket);
      
      this.isResultViewMode.set(mode === 'view');
      this.showResultModal.set(true);
    }
  }

  //===========================================================================
  //METODO AUXILIAR PARA TRANSFORMAR LISTA PLANA EN GRUPOS DE TICKETS
  //===========================================================================
  private groupDeliverablesByTicket(deliverables: CeeDeliverable[]): TicketDeliverableGroup[] {
    const ticketsMap = new Map<string, TicketDeliverableGroup>();
    
    deliverables.forEach(deliverable => {
      if (!deliverable.ticket_id) return;

      let group = ticketsMap.get(deliverable.ticket_id);

      if (!group) {
        group = {
          ticketId: deliverable.ticket_id,
          ticketNumber: deliverable.ticket?.ticket_number || `ID-${deliverable.ticket_id.split('-')[0].toUpperCase()}`,
          deliverablesCount: 0,
          deliverables: []
        };
        ticketsMap.set(deliverable.ticket_id, group);
      }
      
      group.deliverablesCount++;
      group.deliverables.push(deliverable);
    });

    return Array.from(ticketsMap.values());
  }

  public onResultSuccessfullyRegistered(): void {
    this.showResultModal.set(false);
    this.initializeWorkflow(); 
  }

  // ===================================
  // MÉTODOS DE CIERRE DE FASE (CU-081)
  // ===================================
  public async finalizeCeePhase(): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      '¿Cerrar Fase de Certificación de Entregables?',
      'Al confirmar, el requerimiento avanzará y esta subfase quedará sellada de forma inmutable.'
    );

    // Patrón Early Return: si el usuario cancela en el SweetAlert, no hacemos nada.
    if (!isConfirmed) return;

    this.isSubmitting.set(true); // Bloqueamos la interfaz localmente

    this.ceeApiService.closePhase(this.requirementId()).subscribe({
      next: (response) => {
        this.isSubmitting.set(false);
        this.notificationService.showSuccess('¡Fase Sellada!', 'La certificación de entregables ha concluido exitosamente.');
        
        // Emitimos la respuesta al Orquestador para actualizar el Dashboard
        this.phaseClosed.emit({
          req_id: this.requirementId(),
          phase_actual: 'CEE-C', // El estado al que muta por regla de negocio
          progreso_global: response.progress_percentage
        });
        
        this.initializeWorkflow(); 
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        console.error('Error cerrando la fase CEE:', err.message, err.error);
        
        // PROTECCIÓN UX: Capturamos la validación del Backend (Ej. Fase COE abierta)
        let errorMessage = 'No se pudo cerrar la fase de Certificación de Entregables.';
        
        if (err.status >= 500) {
            errorMessage = 'Ocurrió un error interno en el servidor. Por favor, contacte a soporte.';
        } else if (err.error?.message) {
            // El backend nos dirá exactamente qué regla no se cumplió
            errorMessage = err.error.message;
        }
        
        // Lanzamos la alerta visual adecuada
        if (err.status === 422 || err.status === 403) {
            this.notificationService.showWarning('Cierre Denegado', errorMessage);
        } else {
            this.notificationService.showError('Operación Fallida', errorMessage);
        }
      }
    });
  }
}