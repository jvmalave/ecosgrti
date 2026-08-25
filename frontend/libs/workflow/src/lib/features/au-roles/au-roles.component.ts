import { Component, OnInit, signal, computed, inject, input, output, ProviderToken } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Subject } from 'rxjs';
import { AuApiService } from '../../data-access/services/au-api.service';
import { NotificationService } from '../../data-access/services/notification.services'; 
import { TicketGroup, AuRoleWithRelation, AuRole } from '../../data-access/models/au-workflow.model';
import { AuTicketFormComponent } from './components/au-ticket-form/au-ticket-form.component';
import { AuResultFormComponent } from './components/au-result-form/au-result-form.component';

@Component({
  selector: 'lib-au-roles',
  standalone: true,
  imports: [CommonModule, FormsModule, AuTicketFormComponent, AuResultFormComponent],
  templateUrl: './au-roles.component.html',
  styleUrls: ['./au-roles.component.scss']
})
export class AuRolesComponent implements OnInit {
  
  // Inputs desde el Orquestador
  public requirementId = input.required<string>();
  public rrti = input.required<string>(); 
  
  // Outputs (Cierre de fase y Reactividad)
  public phaseClosed = output<{ req_id: string, phase_actual: string, progreso_global: number }>();
  public requireDashboardRefresh = output<void>();

  // Inyección de Dependencias
  private auApiService = inject(AuApiService);
  private notificationService = inject(NotificationService);
  
  // Api Token Global y Subject para el Dashboard
  private readonly apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private readonly workflowApiUrl = `${this.apiUrl}/workflow`;
  private readonly auApiUrl = `${this.workflowApiUrl}/au`;
  public refreshDashboard$ = new Subject<void>();

  // Estado Central (Signals)
  public isLoading = signal<boolean>(true);
  public isSubmitting = signal<boolean>(false);
  public isPhaseClosed = signal<boolean>(false);
  
  public rolesDataset = signal<AuRole[]>([]);
  public searchTerm = signal<string>('');
  
  // Roles seleccionados mediante checkbox para armar un nuevo ticket
  public rolesToProcess = signal<string[]>([]);

  public selectedRolesData = computed(() => {
    const selectedIds = this.rolesToProcess();
    return this.rolesDataset().filter(role => selectedIds.includes(role.id));
  }); 

  // ==========================================
  // CONTROLES DE MODALES
  // ==========================================
  public showRolesStatusModal = signal<boolean>(false); // Modal Estatus (Vista Tripartita)
  public showTicketModal = signal<boolean>(false);      // Modal para crear Ticket
  public showResultModal = signal<boolean>(false);      // Modal para registrar Dictamen (CU-064)

  public activeTicketId = signal<string | null>(null);
  public activeTicketNumber = signal<string>('');
  public isResultViewMode = signal<boolean>(false);

  // ==========================================
  // BUSCADOR UNIFICADO Y ROLES
  // ==========================================
  
  private matchesSearch(role: AuRole, term: string): boolean {
    if (!term) return true;
    const roleName = (role.requirement_role?.role_name || role.role_name || '').toLowerCase();
    const ticketNum = (role.ticket?.ticket_number || '').toLowerCase();
    
    return roleName.includes(term) || ticketNum.includes(term);
  }

  // Roles Pendientes (Filtrados)
  public filteredPending = computed(() => {
    const term = this.searchTerm().toLowerCase().trim();
    return this.rolesPorAsignarTotal().filter(role => this.matchesSearch(role, term));
  });

  // Tickets en Proceso (Filtrados y luego Agrupados)
  public ticketsInProgress = computed(() => {
    const term = this.searchTerm().toLowerCase().trim();
    const inProgress = this.rolesDataset().filter(r => r.status === 'IN_PROGRESS');
    const filtered = inProgress.filter(role => this.matchesSearch(role, term));
    return this.groupRolesByTicket(filtered);
  });

  // Tickets Cerrados (Filtrados y luego Agrupados)
  public closedTicketsGroups = computed(() => {
    const term = this.searchTerm().toLowerCase().trim();
    const assigned = this.rolesDataset().filter(r => r.status === 'ASSIGNED');
    const filtered = assigned.filter(role => this.matchesSearch(role, term));
    return this.groupRolesByTicket(filtered);
  });

  // ==========================================
  // COMPUTADOS: MODAL ESTATUS (Vista Tripartita)
  // ==========================================
  public rolesPorAsignarTotal = computed(() => this.rolesDataset().filter(r => r.status === 'PENDING_AU'));
  public rolesEnProcesoTotal  = computed(() => this.rolesDataset().filter(r => r.status === 'IN_PROGRESS'));
  public rolesAsignadosTotal  = computed(() => this.rolesDataset().filter(r => r.status === 'ASSIGNED'));

  // Hard Gate Client-Side
  public canClosePhase = computed(() => {
    const total = this.rolesDataset().length;
    return total > 0 && this.rolesAsignadosTotal().length === total;
  });

  ngOnInit(): void {
    this.initializeWorkflow();
  }

  public initializeWorkflow(): void {
    this.isLoading.set(true);
    this.rolesToProcess.set([]); 

    this.auApiService.initRoles(this.requirementId()).subscribe({
      next: (response) => {
        this.rolesDataset.set(response.roles);
        this.isLoading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        console.error('Error inicializando AU:', err);
        this.notificationService.showError('Acceso Denegado', 'No se pudieron inicializar los roles de Asignación de Usuarios.');
      }
    });
  }

  // ==========================================
  // MÉTODOS OPERATIVOS Y EVENTOS
  // ==========================================
  
  public onSearchTermChange(term: string): void {
    this.searchTerm.set(term);
  }

  public toggleRoleSelection(roleId: string, event: Event): void {
    const isChecked = (event.target as HTMLInputElement).checked;
    this.rolesToProcess.update(current => {
      if (isChecked) return [...current, roleId];
      return current.filter(id => id !== roleId);
    });
  }

  // ==========================================
  // CONTROLADORES DE APERTURA DE MODALES
  // ==========================================
  
  public openRolesStatusModal(): void {
    this.showRolesStatusModal.set(true);
  }

  public openTicketModal(): void {
    this.showTicketModal.set(true);
  }

  /**
   * CU-064: Abre el modal de Dictamen para evaluar granularmente los roles
   */
  public openResultModal(ticketId: string, ticketNumber: string, mode: 'create' | 'view'): void {
    this.activeTicketId.set(ticketId);
    this.activeTicketNumber.set(ticketNumber);
    this.isResultViewMode.set(mode === 'view');

    const rolesForThisTicket = this.rolesDataset().filter(r => r.ticket_id === ticketId);
    this.rolesToProcess.set(rolesForThisTicket.map(r => r.id)); 

    this.showResultModal.set(true);
  }

  public closeAllModals(): void {
    this.showRolesStatusModal.set(false);
    this.showTicketModal.set(false);
    this.showResultModal.set(false);
    this.activeTicketId.set(null);
  }

  // ==========================================
  // UTILERÍA: AGRUPACIÓN DE ROLES EN TICKETS
  // ==========================================
  private groupRolesByTicket(roles: AuRole[]): TicketGroup[] {
    const groups = roles.reduce((acc: Record<string, TicketGroup>, role: AuRole) => {
      const tId = role.ticket_id || 'SIN_TICKET';
      
      if (!acc[tId]) {
        const roleData = role as AuRoleWithRelation;
        
        acc[tId] = {
          ticketId: tId,
          ticketNumber: roleData.ticket?.ticket_number || 'Ticket En Trámite',
          rolesCount: 0,
          roles: []
        };
      }
      
      acc[tId].rolesCount++;
      acc[tId].roles.push(role);
      
      return acc;
    }, {} as Record<string, TicketGroup>);
    
    return Object.values(groups);
  }

 // ==========================================
  // Cierre global delegado al backend (CU-065)
  // ==========================================
  public async finalizeAuPhase(): Promise<void> {
    // 1. Barrera Estricta: Quórum Absoluto
    if (!this.canClosePhase()) {
      this.notificationService.showError('Restricción de Calidad', 'Todos los roles deben estar asignados para cerrar la fase.');
      return;
    }

    // 2. Modal de Confirmación (SweetAlert)
    const isConfirmed = await this.notificationService.confirm(
      '¿Deseas cerrar la Fase Asignación a Usuarios?',
      'Al confirmar, esta fase quedará sellada de forma <b>inmutable</b> y la información pasará a modo de <strong>solo lectura</strong>.',
      '<i class="fa-solid fa-flag-checkered me-1"></i> Sí, Cerrar Fase'
    );

    // Patrón Early Return: si el usuario cancela, salimos silenciosamente.
    if (!isConfirmed) return;

    this.isLoading.set(true); // Bloqueamos el botón y mostramos el spinner
    
    this.auApiService.finalizeAuPhase(this.requirementId()).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        this.notificationService.showSuccess(
          '¡Fase Sellada!', 
          `El requerimiento ha concluido exitosamente. Progreso global: ${response.data.progreso_global}%.`
        );
        
        // Sello de estado visual (Reconfigura la vista histórica)
        this.isPhaseClosed.set(true); 
        
        // Emitimos el output hacia el orquestador principal
        this.phaseClosed.emit({
          req_id: response.data.req_id,
          phase_actual: 'AU-C', 
          progreso_global: response.data.progreso_global
        });

        // Detonamos la recarga reactiva de los tableros
        this.requireDashboardRefresh.emit(); 
        
        // Cerramos cualquier modal anidado si lo hubiera
        this.closeAllModals(); 
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        console.error('Error cerrando la fase AU:', err.message, err.error);
        
        // PROTECCIÓN UX: Manejo dinámico del error enviado por Laravel
        let errorMessage = 'No se pudo cerrar la fase de Asignación de Usuarios.';
        
        if (err.status >= 500) {
            errorMessage = 'Ocurrió un error interno en el servidor. Por favor, revise el log de Laravel (Storage/logs).';
        } else if (err.error?.message) {
            // Capturamos el mensaje exacto de nuestra validación (abort 422)
            errorMessage = err.error.message;
        }
        
        // Lanzamos el SweetAlert adecuado según el código HTTP
        if (err.status === 422 || err.status === 403) {
            this.notificationService.showWarning('Cierre Denegado', errorMessage);
        } else {
            this.notificationService.showError('Operación Fallida', errorMessage);
        }
      }
    });
  }
}