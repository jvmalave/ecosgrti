import { Component, OnInit, inject, input, signal, computed, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DtWorkflowService } from '../../data-access/services/dt-workflow.service';
import { NotificationService } from '../../data-access/services/notification.services';
import { DtRole } from '../../data-access/models/dt.model';

@Component({
  selector: 'lib-dt-roles-list-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './dt-roles-list-modal.component.html',
  styleUrls: ['./dt-roles-list-modal.component.scss']
})
export class DtRolesListModalComponent implements OnInit {
  
  //------------------------------------
  // INYECION DE DEPENDENCIAS
  //------------------------------------

  private dtWorkflowService = inject(DtWorkflowService);
  private notificationService = inject(NotificationService);

  //------------------------------------
  // INPUTS Y OUTPUTS REACTIVOS
  //------------------------------------

  requirementId = input.required<string>();
  openRegisterModal = output<DtRole>();

  //------------------------------------
  // VARIABLES REACTIVAS DEL COMPONENTE (Signals y Computed)
  //------------------------------------

  // Estado centralizado usando Signals
  isLoading = signal<boolean>(true);
  roles = signal<DtRole[]>([]);

  // Selectores derivados (Computed Signals)
  inProgressRoles = computed(() => 
    this.roles().filter(role => role.status === 'IN_PROGRESS')
  );
  closedRoles = computed(() => 
    this.roles().filter(role => role.status === 'CLOSED')
  );

  ngOnInit(): void {
    this.fetchRoles();
  }

  //------------------------------------
  // METODOS DEL COMPONENTE
  //------------------------------------

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
}