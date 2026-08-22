import { Component, OnInit, inject, input, signal, computed, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CerRolesStore } from '../../data-access/store/cer-roles.store';
import { CerApiService } from '../../data-access/services/cer-api.service';
import { CerTicketFormComponent } from './component/cer-ticket-form/cer-ticket-form.component';
import { CerResultFormComponent } from './component/cer-result-form/cer-result-form.component';
import { CerRole, TicketGroup } from '../../data-access/models/cer-workflow.model';
import { NotificationService,} from '../../data-access/services/notification.services';
import { HttpErrorResponse } from '@angular/common/http';


@Component({
  selector: 'lib-cer-roles',
  standalone: true,
  imports: [
    CommonModule, 
    FormsModule, 
    CerTicketFormComponent, 
    CerResultFormComponent
  ], 
  templateUrl: './cer-roles.component.html',
  styleUrls: ['./cer-roles.component.scss']
})
export class CerRolesComponent implements OnInit {
  // ==========================================
  // INYECCIÓN DE DEPENDENCIAS
  // ==========================================
  public readonly store = inject(CerRolesStore);
  private readonly cerApiService = inject(CerApiService);
  private readonly notificationService = inject(NotificationService);

  // ==========================================
  // INPUTS Y OUTPUTS
  // ==========================================
  public requirementId = input.required<string>();
  public rrti = input.required<string>();

  // Recibe el estado actual del requerimiento desde el padre (ej. 'CER-I', 'CER-C')
  public reqPhase = input<string>('CER-I');
  
  // Emite el payload de respuesta de Laravel para actualizar el Dashboard instantáneamente
  public phaseClosed = output<{req_id: string, phase_actual: string, progreso_global: number}>();

  // ==========================================
  // SIGNALS Y COMPUTED PARA EL MODAL DE TICKETS
  // ==========================================
  public showTicketModal = signal<boolean>(false);
  public rolesToProcess = signal<string[]>([]);
  public selectedRolesData = computed(() => {
    return this.store.filteredPending().filter(r => this.rolesToProcess().includes(r.id));
  });

  public isSubmitting = signal<boolean>(false);

  // ==========================================
  // COMPUTED PARA TABLAS AGRUPADAS POR TICKET
  // ==========================================
  public ticketsInProgress = computed<TicketGroup[]>(() => {
    // 1. Extraemos los roles crudos de la lista maestra para evadir el filtro por nombre
    const roles = this.store.roles().filter(r => r.status === 'IN_PROGRESS');
    let groups = this.groupRolesByTicket(roles);

    // 2. Filtramos los grupos estrictamente por el número de ticket
    const term = this.store.searchTerm().trim().toLowerCase();
    if (term) {
      groups = groups.filter(g => g.ticketNumber.toLowerCase().includes(term));
    }

    return groups;
  });

  public ticketsCertified = computed<TicketGroup[]>(() => {
    // 1. Extraemos los roles certificados crudos
    const roles = this.store.roles().filter(r => r.status === 'CERTIFIED');
    let groups = this.groupRolesByTicket(roles);

    // 2. Filtramos estrictamente por ticket
    const term = this.store.searchTerm().trim().toLowerCase();
    if (term) {
      groups = groups.filter(g => g.ticketNumber.toLowerCase().includes(term));
    }

    return groups;
  });

  // ==========================================
  // HARD GATE: QUÓRUM DE CIERRE GLOBAL
  // ==========================================
  // Detecta si la fase ya está cerrada (Cualquier fase distinta a CER-I implica que ya avanzó)
  public isPhaseClosed = computed<boolean>(() => {
    return this.reqPhase() !== 'CER-I';
  });
  

  public canClosePhase = computed<boolean>(() => {
    const hasRoles = this.store.roles().length > 0;
    const noPending = this.store.filteredPending().length === 0;
    const noInProgress = this.store.filteredInProgress().length === 0;
    // No permitir cerrar si YA está cerrada
    return hasRoles && noPending && noInProgress && !this.isPhaseClosed();
  });

  // ==========================================
  // SIGNALS PARA EL MODAL DE RESULTADOS
  // ==========================================
  public showResultModal = signal<boolean>(false);
  public isResultViewMode = signal<boolean>(false);
  public activeTicketId = signal<string>('');
  public activeTicketNumber = signal<string>('');
  public activeRoles = signal<CerRole[]>([]);

  ngOnInit(): void {
    this.initializeWorkflow();
  }

  // ==========================================
  // METODO PARA INICIALIZACIÓN DE LA FASE
  // ==========================================
  private initializeWorkflow(): void {
    this.cerApiService.initRoles(this.requirementId()).subscribe({
      next: (response) => this.store.setRoles(response.roles),
      error: (error) => console.error('Error inicializando la fase CER:', error)
    });
  }

  // ==========================================
  // MÉTODOS PARA BUSQUEDA DE ROLES PENDIENTES
  // ==========================================
  public onSearchTermChange(term: string): void {
    this.store.updateSearchTerm(term);
  }

  // ==========================================
  // MÉTODOS PARA SELECCIONAR ROLES
  // ==========================================
  public toggleRoleSelection(roleId: string, event: Event): void {
    const isChecked = (event.target as HTMLInputElement).checked;
    this.rolesToProcess.update(currentSelected => {
      if (isChecked) {
        return [...currentSelected, roleId];
      } else {
        return currentSelected.filter(id => id !== roleId);
      }
    });
  }

  // ==========================================
  // MÉTODOS PARA ABRIR EL MODAL DE TICKETS
  // ==========================================
  public openTicketModal(): void {
    if (this.rolesToProcess().length > 0) {
      this.showTicketModal.set(true);
    }
  }

  // ==========================================
  // MÉTODOS PARA EL MODAL DE TICKETS
  // ==========================================
  public onTicketSuccessfullyCreated(): void {
    this.showTicketModal.set(false);
    this.rolesToProcess.set([]);
    this.initializeWorkflow(); 
  }

  // ==========================================
  // MÉTODOS PARA ABRIR Y CERRAR EL DICTAMEN
  // ==========================================
  public openResultModal(ticketId: string | null, mode: 'create' | 'view' = 'create'): void {
    if (!ticketId) return;

    const allRoles = [
      ...this.store.filteredPending(), 
      ...this.store.filteredInProgress(), 
      ...this.store.filteredCertified()
    ];
    
    const rolesInTicket = allRoles.filter(r => r.ticket_id === ticketId);
    
    if (rolesInTicket.length > 0) {
      this.activeTicketId.set(ticketId);
      const realTicketNumber = rolesInTicket[0].ticket?.ticket_number || `ID-${ticketId.split('-')[0].toUpperCase()}`;
      
      this.activeTicketNumber.set(realTicketNumber); 
      this.activeRoles.set(rolesInTicket);
      
      this.isResultViewMode.set(mode === 'view');
      this.showResultModal.set(true);
    }
  }

  //===========================================================================
  //METODO AUXILIAR PARA TRANSFORMAR LISTA PLANA DE ROLES EN GRUPOS DE TICKETS
  //===========================================================================
  private groupRolesByTicket(roles: CerRole[]): TicketGroup[] {
    const ticketsMap = new Map<string, TicketGroup>();
    
    roles.forEach(role => {
      if (!role.ticket_id) return;

      // Busca el grupo correspondiente
      let group = ticketsMap.get(role.ticket_id);

      // Si no existe, lo inicia y lo guarda en el Map
      if (!group) {
        group = {
          ticketId: role.ticket_id,
          ticketNumber: role.ticket?.ticket_number || `ID-${role.ticket_id.split('-')[0].toUpperCase()}`,
          rolesCount: 0,
          roles: []
        };
        ticketsMap.set(role.ticket_id, group);
      }
      
      group.rolesCount++;
      group.roles.push(role);
    });

    return Array.from(ticketsMap.values());
  }

// ==========================================
// METODO PARA ABRIR EL MODAL DE RESULTADOS
// ==========================================
  public onResultSuccessfullyRegistered(): void {
    this.showResultModal.set(false);
    this.initializeWorkflow(); 
  }

  // ==========================================
  // MÉTODOS DE CIERRE DE FASE
  // ==========================================
  public async finalizeCerPhase(): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      '¿Cerrar Fase de Certificación?',
      'Al confirmar, el requerimiento avanzará y estafase quedará sellada de forma inmutable.'
    );

    // Patrón Early Return: si cancela el modal, salimos.
    if (!isConfirmed) return;

    this.isSubmitting.set(true); // Bloqueamos el botón y mostramos spinner

    this.cerApiService.closePhase(this.requirementId()).subscribe({
      next: (response) => {
        this.isSubmitting.set(false);
        this.notificationService.showSuccess('¡Fase Sellada!', 'La certificación de roles ha concluida exitosamente.');
        
        // Emitimos la respuesta al padre construyendo el objeto con nuestros datos locales + el backend
        this.phaseClosed.emit({
          req_id: this.requirementId(),
          phase_actual: 'CER-C', // Sabemos por regla de negocio que muta a este estado
          progreso_global: response.progress_percentage
        });
        
        this.initializeWorkflow(); 
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        console.error('Error cerrando la fase:', err.message, err.error);
        
        // PROTECCIÓN UX: Manejo dinámico del error enviado por Laravel
        let errorMessage = 'No se pudo cerrar la fase.';
        
        if (err.status >= 500) {
            errorMessage = 'Ocurrió un error interno en el servidor. Por favor, contacte a soporte.';
        } else if (err.error?.message) {
            // Aquí capturamos el mensaje del backend (Ej: "No puede cerrar CER porque PI sigue abierta")
            errorMessage = err.error.message;
        }
        
        // Lanzamos el SweetAlert adecuado según el tipo de rechazo HTTP
        if (err.status === 422 || err.status === 403) {
            this.notificationService.showWarning('Cierre Denegado', errorMessage);
        } else {
            this.notificationService.showError('Operación Fallida', errorMessage);
        }
      }
    });
  }
}
