import { Component, OnInit, inject, input, signal, computed, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DtWorkflowService } from '../../data-access/services/dt-workflow.service';
import { NotificationService } from '../../data-access/services/notification.services';
import { DtRole, DtRoleInitResponse  } from '../../data-access/models/dt.model';
import {HttpErrorResponse} from '@angular/common/http';
import {Router} from '@angular/router';
import { DtRegistersModalComponent } from '../dt-registers-modal/dt-registers-modal.component';


@Component({
  selector: 'lib-dt-roles-list-modal',
  standalone: true,
  imports: [CommonModule, DtRegistersModalComponent],
  templateUrl: './dt-roles-list-modal.component.html',
  styleUrls: ['./dt-roles-list-modal.component.scss']
})
export class DtRolesListModalComponent implements OnInit {
  
  //------------------------------------
  // INYECION DE DEPENDENCIAS
  //------------------------------------

  private dtWorkflowService = inject(DtWorkflowService);
  private notificationService = inject(NotificationService);
  private router = inject(Router);

  //------------------------------------
  // INPUTS Y OUTPUTS REACTIVOS
  //------------------------------------

  requirementId = input.required<string>();
  rrti = input.required<string>();
  openRegisterModal = output<DtRole>();
  closeModal = output<void>();
  reqStatus = input.required<string>();

  //------------------------------------
  // VARIABLES REACTIVAS DEL COMPONENTE (Signals y Computed)
  //------------------------------------


  // Estado centralizado usando Signals
  isLoading = signal<boolean>(true);
  roles = signal<DtRole[]>([]);

  selectedRoleForRegisters = signal<DtRole | null>(null);

  // Signal para la búsqueda reactiva
  searchTerm = signal<string>('');

  // Signal para el estado de la fase
  currentPhaseStatus = signal<string>('');

  // Selectores derivados (Computed Signals)
  inProgressRoles = computed(() => 
    this.roles().filter(role => role.status === 'IN_PROGRESS')
  );
  

  // Filtrado reactivo para Roles en Proceso (Aplicando la búsqueda)
  activeRoles = computed(() => {
    const term = this.searchTerm().toLowerCase();
    return this.roles().filter(r => 
      r.status === 'IN_PROGRESS' && r.name.toLowerCase().includes(term)
    );
  });

  // Filtrado reactivo para Roles Cerrados (Aplicando la búsqueda)
  closedRoles = computed(() => {
    const term = this.searchTerm().toLowerCase();
    return this.roles().filter(r => 
      r.status === 'CLOSED' && r.name.toLowerCase().includes(term)
    );
  });

  

  // Hard-Gate para habilitar el cierre de la Fase DT
  // Se habilita SOLO si hay roles creados y TODOS están en estado 'CLOSED'
  isPhaseCloseEnabled = computed(() => {
    const allRoles = this.roles();
    if (allRoles.length === 0) return false;
    return allRoles.every(r => r.status === 'CLOSED');
  });

  ngOnInit(): void {
    this.fetchRoles();
    this.currentPhaseStatus.set(this.reqStatus());
  }

  //------------------------------------
  // METODOS DEL COMPONENTE
  //------------------------------------
  loadRoles(): void {
    console.log('1. Solicitando roles para el Requerimiento:', this.requirementId());
    
    this.dtWorkflowService.getRolesInit(this.requirementId()).subscribe({
      next: (data: DtRoleInitResponse) => {
        // Protección extra: Si roles_list viene undefined, seteamos un arreglo vacío
        this.roles.set(data.roles_list || []); 
        this.isLoading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.notificationService.showError('Error', 'No se pudieron cargar los roles.');
        this.isLoading.set(false);
        console.error('Detalle del error HTTP:', err.message, err.error);
      }
    });
  }

  // Actualiza el término de búsqueda desde el input HTML
  updateSearch(event: Event): void {
    const inputElement = event.target as HTMLInputElement;
    this.searchTerm.set(inputElement.value);
  }

  // Carga los roles asociados al requerimiento
  fetchRoles(): void {
    this.isLoading.set(true);
    this.dtWorkflowService.getRolesInit(this.requirementId()).subscribe({
      next: (response) => {
        this.roles.set(response.roles_list);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.isLoading.set(false);
        this.notificationService.showError(
          'Error de Carga', 
          'No se pudo sincronizar la lista de roles técnicos.'
        );
        console.error(err);
      }
    });
  }
  // Cambia el estado de un rol
  toggleRoleStatus(role: DtRole): void {
    const action = role.status === 'IN_PROGRESS' ? 'CLOSE' : 'REOPEN';
    
    this.dtWorkflowService.changeRoleStatus(role.id, action).subscribe({
      next: (response) => {
        // Actualización inmutable reactiva de la señal
        this.roles.update(currentRoles => 
          currentRoles.map(r => 
            r.id === response.role_id ? { ...r, status: response.new_status } : r
          )
        );
        
        // Notificación de éxito
        const successMsg = action === 'CLOSE' ? 'Rol cerrado exitosamente.' : 'Rol reabierto exitosamente.';
        this.notificationService.toastSuccess(successMsg);
      },
      error: (err) => {
        // Manejo de reglas de negocio bloqueadas por Backend (pest)
        if (err.status === 422) {
          this.notificationService.showWarning(
            'Acción Denegada', 
            'No se puede cerrar el rol porque requiere al menos un registro técnico en la bitácora.'
          );
        } else if (err.status === 403) {
          this.notificationService.showError(
            'Acceso Denegado', 
            'La subfase global está cerrada o en una etapa superior.'
          );
        } else {
          this.notificationService.showError(
            'Error', 
            'Ocurrió un problema al procesar la transición de estado.'
          );
        }
      }
    });
  }
  // Abre el modal de registros
  viewRegisters(role: DtRole): void {
    this.openRegisterModal.emit(role);
  }

  // Funciones de acción (Integrar con tus servicios)
  
  openRegisters(role: DtRole): void {
    // Al setear el rol en la señal, la directiva @if en el HTML renderizará el modal hijo
    this.selectedRoleForRegisters.set(role);
  }

  /**
   * Reabre un rol técnico cerrado previa confirmación (Acción REOPEN)
   */
  async reopenRole(role: DtRole): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      'Reabrir Rol Técnico',
      `¿Estás seguro que deseas reabrir el rol "${role.name}"? Esto permitirá agregar nuevas registros en la bitácoras a este rol.`
    );

    if (isConfirmed) {
      // Llamamos al servicio con la acción REOPEN
      this.dtWorkflowService.changeRoleStatus(role.id, 'REOPEN').subscribe({
        next: () => {
          // Actualización inmutable: movemos el rol de vuelta a "EN PROCESO"
          this.roles.update(currentRoles =>
            currentRoles.map(r => r.id === role.id ? { ...r, status: 'IN_PROGRESS' } : r)
          );
          
          this.notificationService.toastSuccess('Rol técnico reabierto exitosamente.');
        },
        error: (err: HttpErrorResponse) => {
          // El backend arrojará un 403 si la fase global ya está cerrada, lo capturamos aquí
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
   * Cierra un rol técnico individual previa confirmación
   */
  async closeRole(role: DtRole): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      'Cerrar Rol Técnico',
      `¿Estás seguro que deseas cerrar el rol "${role.name}"? Esta acción bloqueará la adición de nuevas bitácoras.`
    );

    if (isConfirmed) {
      this.dtWorkflowService.changeRoleStatus(role.id, 'CLOSE').subscribe({
        next: () => {
          // Actualización inmutable de la señal: Angular moverá automáticamente el rol 
          // de la tabla "En Proceso" a la tabla "Cerrados" gracias a los signals computados.
          this.roles.update(currentRoles =>
            currentRoles.map(r => r.id === role.id ? { ...r, status: 'CLOSED' } : r)
          );
          
          this.notificationService.toastSuccess('Rol técnico cerrado exitosamente.');
        },
        error: (err: HttpErrorResponse) => {
          this.notificationService.showError('Error Transaccional', 'No se pudo procesar el cierre del rol.');
          console.error('Fallo en closeRole:', err.message);
        }
      });
    }
  }

  /**
   * Cierra la fase completa de Diseño Técnico
   */
  async closeDtPhase(): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      'Cerrar Fase de Diseño Técnico',
      'Todos los roles han sido cerrados. ¿Deseas dar por finalizada la fase de Diseño Técnico para este requerimiento?'
    );

    if (isConfirmed) {
      // Llamada al servicio para cerrar la fase global
      this.dtWorkflowService.closePhase(this.requirementId()).subscribe({
        next: () => {
          this.currentPhaseStatus.set('DT-C');
          this.notificationService.toastSuccess('Fase de Diseño Técnico finalizada.');
          
          
          // Emitimos una señal al Dashboard para que recargue la tabla principal
          this.dtWorkflowService.refreshDashboard$.next();
          
          // Cerramos el modal actual para retornar al orquestador
          this.closeModal.emit(); 
        },
        error: (err: HttpErrorResponse) => {
          this.notificationService.showError('Error de Sistema', 'Hubo un problema al intentar cerrar la fase global.');
          console.error('Fallo en closeDtPhase:', err.message);
        }
      });
    }
  }
}