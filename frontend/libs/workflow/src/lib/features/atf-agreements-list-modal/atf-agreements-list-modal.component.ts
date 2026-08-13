// Ruta: frontend/libs/workflow/src/lib/ui-atf-agreements-list-modal/atf-agreements-list-modal.component.ts

import { Component, inject, input, output, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { WorkflowApiService,  } from '../../data-access/services/atf.service';
import { AtfAgreementDetail } from '../../data-access/models/atf-agreement.model';
import Swal from 'sweetalert2';
import { UpdateManagementTypeModalComponent } from '../update-management-type-modal/update-management-type-modal.component';
import { AtfRolesListComponent } from '../atf-roles-list/atf-roles-list.component';
import { AtfDeliverablesListComponent } from '../atf-deliverables-list-modal/atf-deliverables-list-modal.component';
import { AtfClosureService } from '../../data-access/services/atf-closure.service';
import { ClosureReadinessResponse } from '../../data-access/models/atf-agreement.model';
import { NotificationService } from '../../data-access/services/notification.services';



@Component({
  selector: 'lib-atf-agreements-list-modal',
  standalone: true,
  imports: [CommonModule, UpdateManagementTypeModalComponent, AtfRolesListComponent, AtfDeliverablesListComponent],
  templateUrl: './atf-agreements-list-modal.component.html',
  styleUrl: './atf-agreements-list-modal.component.scss'
})
export class AtfAgreementsListModalComponent implements OnInit {
  private workflowApi = inject(WorkflowApiService);
  private atfClosureService = inject(AtfClosureService);
  private notificationService = inject(NotificationService)
  
  
  public requirementId = input.required<string>();
  public requirementCode = input<string>('');
  public isAtfOpen = input<boolean>(true);
  public closeModal = output<void>();
  public openCreateModal = output<void>();
  public currentManagementType = input.required<string>();

  public agreements = signal<AtfAgreementDetail[]>([]);
  public isLoading = signal<boolean>(true);
  public viewAgreementDetail = output<AtfAgreementDetail>();
  public isUpdateTypeModalOpen = signal<boolean>(false);

  public isRolesListOpen = signal<boolean>(false);
  public isDeliverablesListOpen = signal<boolean>(false)

  public isReadyToClose = signal<boolean>(false);
  public closureReasons = signal<string[]>([]);
  public isAtfClosed = signal<boolean>(false);
  

  // Gatekeeper Nivel 1: ¿Tenemos al menos un acuerdo registrado?
  // (Nota: Ajusta 'this.agreements()' al nombre exacto del Signal/Variable que guarda tu lista de acuerdos)
  public hasAgreements = computed(() => this.agreements().length > 0);
  
  // Gatekeeper Nivel 2: Compuertas por Tipología (Evaluación estricta)
  public canManageRoles = computed(() => {
    // Aseguramos que sea string, limpiamos espacios y convertimos a mayúsculas
    const type = (this.currentManagementType() || '').trim().toUpperCase();
    
    // Usamos includes('ROL') para atrapar 'ROL' o 'ROLES'
    return this.hasAgreements() && (type.includes('ROL') || type === 'MIXTO');
  });

  public canManageDeliverables = computed(() => {
    const type = (this.currentManagementType() || '').trim().toUpperCase();
    
    // Usamos includes('ENTREGABLE') para atrapar 'ENTREGABLE' o 'ENTREGABLES'
    return this.hasAgreements() && (type.includes('ENTREGABLE') || type === 'MIXTO');
  });

  public typeUpdated = output<{ tipo_gestion: string, progreso_global: number }>();

  public atfPhaseClosed = output<void>();

  
  ngOnInit(): void {
    //console.log('Código recibido en Padre:', this.requirementCode());
    this.loadAgreements();
    this.verifyReadiness();
  }

  public loadAgreements(): void {
    this.isLoading.set(true);
    this.workflowApi.getAgreements(this.requirementId()).subscribe({
      next: (res) => {
        this.agreements.set(res.data);
        this.isLoading.set(false);
      },
      error: () => this.isLoading.set(false)
    });
  }

  public viewAgreement(agreement: AtfAgreementDetail): void {

    this.viewAgreementDetail.emit(agreement);
  }

  public deleteAgreement(agreementId: string): void {
    Swal.fire({
      title: '¿Estas seguro de eliminar acuerdo?',
      text: "Esta acción removerá el acuerdo de la lista ",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33', // Rojo peligro
      cancelButtonColor: '#6c757d', // Gris secundario
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      
      if (result.isConfirmed) {
        // Ejecutamos la petición HTTP
        this.workflowApi.deleteAgreement(this.requirementId(), agreementId).subscribe({
          next: () => {
            // Mostramos alerta de éxito (Toast o Modal pequeño)
            Swal.fire(
              '¡Eliminado!',
              'El acuerdo técnico ha sido removido.',
              'success'
            );
            
            // Refrescamos la vista local o disparamos el evento para que el Dashboard recargue
            // Si tienes un array local signal llamado 'agreements', puedes filtrarlo directamente:
            this.agreements.update(items => items.filter(item => item.id !== agreementId));
            
            // O emitir una señal global de actualización:
            this.workflowApi.refreshDashboard$.next();
          },
          error: (err) => {
            console.error('Error al eliminar:', err);
            Swal.fire(
              'Error',
              'No se pudo eliminar el acuerdo. Intente de nuevo.',
              'error'
            );
          }
        });
      }
      
    });
  }

  // Método para manejar la actualización exitosa
  public onTypeUpdated(event: { tipo_gestion: string, progreso_global: number }): void {
    this.isUpdateTypeModalOpen.set(false);
    
    // Se pasa el dato hacia arriba (Al Dashboard)
    this.typeUpdated.emit(event); 
  }

  // Métodos placeholder para abrir los modales de gestión
  public openRolesManagement(): void {
    this.isRolesListOpen.set(true);
  }

  public openDeliverablesManagement(): void {
    if (this.canManageDeliverables()) {
      this.isDeliverablesListOpen.set(true);
    }
  }

  // ==========================================
  //  LÓGICA DE CIERRE DE FASE (HARD GATE)
  // ==========================================

  /**
   * Consulta al backend si la fase cumple con el quórum mínimo.
   */
  public verifyReadiness(): void {
    this.atfClosureService.checkClosureReadiness(this.requirementId()).subscribe({
      next: (response: ClosureReadinessResponse) => {
        
        this.isReadyToClose.set(response.ready);
        this.closureReasons.set(response.reasons || []);
      
        if (response.already_closed) {
           this.isAtfClosed.set(true); // Activa el Banner de Inmutabilidad
          this.atfPhaseClosed.emit(); 
        }
      },
      error: (err) => {
        // Este bloque solo atrapará errores reales de servidor (500) o red (0)
        console.error('Error de red verificando readiness:', err);
      }
    });
  }
  /**
   * Invocación del modal de confirmación
   */
  public confirmClosure(): void {
    // Bloqueo de seguridad en el cliente
    if (!this.isReadyToClose()) {
      Swal.fire({
        title: 'Quórum Insuficiente',
        text: this.closureReasons().join(' '),
        icon: 'warning',
        confirmButtonColor: '#8e1482'
      });
      return;
    }

    // Modal de advertencia de Inmutabilida
    
    Swal.fire({
      title: '¿Estás seguro de cerrar la Fase Análisis Técnico Funcional?',
      text: 'Esta acción es definitiva. Todos los Acuerdos, Roles y Entregables pasarán a ser de Solo Lectura.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#8e1482', // Color brand
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sí, Cerrar Fase',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.executeClosure();
      }
    });
  }

  /**
   * Ejecución Atómica del Cierre y actualización de estado
   */
  private executeClosure(): void {
    this.atfClosureService.closeAtfPhase(this.requirementId()).subscribe({
      next: (res) => {
        // 1. Notificación de éxito
        this.notificationService.toastSuccess(res.message)
        // Swal.fire('Operación Exitosa', res.message, 'success');
        
        // 2. Transición de Estado Reactivo
        this.isAtfClosed.set(true); 
        this.atfPhaseClosed.emit(); // Notificamos al Dashboard principal
        
        // 3. Notificamos a través del bus de eventos para bloquear componentes hijos
        this.atfClosureService.atfClosed$.next(); 
      },
      error: (err) => {
        const errorMsg = err.error?.message || 'Error al procesar el cierre de la fase ATF.';
        Swal.fire('Error Transaccional', errorMsg, 'error');
      }
    });
  }
} 
