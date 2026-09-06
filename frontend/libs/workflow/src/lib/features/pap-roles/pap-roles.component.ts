import {
  Component,
  OnInit,
  inject,
  input,
  signal,
  computed,
  output,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
//import { HttpErrorResponse } from '@angular/common/http';

import { PapRolesStore } from '../../data-access/store/pap-roles.store';
import { PapApiService } from '../../data-access/services/pap-api.service';
import {
  PapRole,
  OrderGroup,
  PapOrderResponse,
  SuccessfulOrder,
} from '../../data-access/models/pap-workflow.model';
import { NotificationService } from '../../data-access/services/notification.services';
import { PapOrderFormComponent } from './component/pap-order-form/pap-order-form.component';
import { PapResultFormComponent } from './component/pap-result-form/pap-result-form.component';
import { HttpErrorResponse } from '@angular/common/http';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { GlobalStatusModalComponent, StatusColumn } from '@ecosgrti/shared';

@Component({
  selector: 'lib-pap-roles',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    PapOrderFormComponent,
    PapResultFormComponent,
    GlobalStatusModalComponent,
  ],
  templateUrl: './pap-roles.component.html',
  styleUrls: ['./pap-roles.component.scss'],
})
export class PapRolesComponent implements OnInit {
  // ==========================================
  // INYECCIÓN DE DEPENDENCIAS
  // ==========================================
  public readonly store = inject(PapRolesStore);
  private readonly papApiService = inject(PapApiService);
  private readonly notificationService = inject(NotificationService);

  // ==========================================
  // INPUTS Y OUTPUTS
  // ==========================================
  public requirementId = input.required<string>();
  public rrti = input.required<string>();
  public reqPhase = input<string>('PAP-I');
  public phaseClosed = output<{
    req_id: string;
    phase_actual: string;
    progreso_global: number;
  }>();
  public frozenPhases = input<string[]>([]);

  // ==========================================
  // ESTADOS LOCALES Y DE INTERFAZ
  // ==========================================
  public isSubmitting = signal<boolean>(false);
  public rolesToProcess = signal<string[]>([]);

  // Filtra de los pendientes solo los seleccionados por el usuario mediante los checkboxes
  public selectedRolesData = computed(() => {
    return this.store
      .filteredPending()
      .filter((r) => this.rolesToProcess().includes(r.id));
  });

  // ==========================================
  // AGRUPACIÓN REACTIVA POR ÓRDENES DE TRANSPORTE
  // ==========================================
  public ordersInProgress = computed<OrderGroup[]>(() => {
    return this.groupRolesByOrder(this.store.filteredInProgress());
  });

  public ordersInProduction = computed<OrderGroup[]>(() => {
    return this.groupRolesByOrder(this.store.filteredInProduction());
  });

  // ==========================================
  // SIGNALS PARA GESTIÓN DE MODALES
  // ==========================================
  public showOrderModal = signal<boolean>(false);
  public showResultModal = signal<boolean>(false);
  public isResultViewMode = signal<boolean>(false);
  public activeOrderId = signal<string>('');
  public activeOrderNumber = signal<string>('');
  public activeRoles = signal<PapRole[]>([]);
  public activeRolesInOrder = signal<PapRole[]>([]);

  // ==========================================
  // MODAL UNIVERSAL: ESTATUS DE PRODUCCIÓN (PAP)
  // ==========================================
  public showPapStatusModal = signal<boolean>(false);

  public statusColumnsData = computed<StatusColumn[]>(() => {
    return [
      {
        title: 'Pendientes',
        icon: 'fa-solid fa-hourglass-half',
        bgClass: 'bg-warning bg-opacity-25',
        textClass: 'text-dark',
        items: this.store.filteredPending().map((r) => ({
          id: r.id,
          name: r.requirement_role?.role_name || 'Rol sin nombre',
        })),
        emptyMessage: 'No hay roles pendientes',
        emptyIcon: 'fa-solid fa-check-double text-warning',
        itemIcon: 'fa-solid fa-circle text-warning fs-6',
      },
      {
        title: 'En Trámite (Transporte)',
        icon: 'fa-solid fa-truck-fast',
        bgClass: 'bg-info bg-opacity-25',
        textClass: 'text-dark',
        items: this.store.filteredInProgress().map((r) => ({
          id: r.id,
          name: r.requirement_role?.role_name || 'Rol sin nombre',
        })),
        emptyMessage: 'Sin órdenes en curso',
        emptyIcon: 'fa-regular fa-folder-open text-info',
        itemIcon: 'fa-solid fa-circle-notch fa-spin text-info',
      },
      {
        title: 'En Producción',
        icon: 'fa-solid fa-server',
        bgClass: 'bg-success bg-opacity-25',
        textClass: 'text-dark',
        items: this.store.filteredInProduction().map((r) => ({
          id: r.id,
          name: r.requirement_role?.role_name || 'Rol sin nombre',
        })),
        emptyMessage: 'Aún no hay roles productivos',
        emptyIcon: 'fa-solid fa-lock text-success',
        itemIcon: 'fa-solid fa-check text-success',
      },
    ];
  });

  // ==========================================
  // INPUTS DEL ORQUESTADOR
  // ==========================================

  // Señal que recibe el estado de la fase anterior (CER)
  public isCerPhaseClosed = input<boolean>(false);

  // ==========================================
  // HARD GATES Y VALIDACIÓN DE CIERRE
  // ==========================================

 // =========================================================
  // HARD GATE: QUÓRUM DE CIERRE GLOBAL (PATRÓN DE LA VERDAD)
  // =========================================================

  // Detecta si PAP ya está cerrada leyendo la historia inmutable del backend
  public isPhaseClosed = computed<boolean>(() => {
    const frozen = this.frozenPhases() || [];
    return frozen.includes('PAP');
  });

  public canClosePhase = computed<boolean>(() => {
    const allRoles = this.store.roles(); 
    
    if (allRoles.length === 0) return false;

    // 🟢 CORRECCIÓN: Contemplamos 'CLOSED' y 'IN_PRODUCTION'
    const allProductive = allRoles.every(r => ['IN_PRODUCTION', 'CLOSED'].includes(r.status));

    const frozen = this.frozenPhases() || [];
    const isCerClosed = frozen.includes('CER');

    return allProductive && isCerClosed && !this.isPhaseClosed();
  });

  ngOnInit(): void {
    this.initializeWorkflow();
  }

  // ==========================================
  // MÉTODOS DE INICIALIZACIÓN Y BÚSQUEDA
  // ==========================================
  public initializeWorkflow(): void {
    this.papApiService.initRoles(this.requirementId()).subscribe({
      next: (response) => this.store.setRoles(response.roles),
      error: (error) =>
        console.error('Error inicializando la fase PAP:', error),
    });
  }

  public onSearchTermChange(term: string): void {
    this.store.updateSearchTerm(term);
  }

  public toggleRoleSelection(roleId: string, event: Event): void {
    const isChecked = (event.target as HTMLInputElement).checked;
    this.rolesToProcess.update((current) =>
      isChecked ? [...current, roleId] : current.filter((id) => id !== roleId),
    );
  }

  // ==========================================
  // MOTOR DE AGRUPACIÓN (ESPEJO DE CER TICKETS)
  // ==========================================
  private groupRolesByOrder(roles: PapRole[]): OrderGroup[] {
    const ordersMap = new Map<string, OrderGroup>();

    roles.forEach((role) => {
      if (!role.order_id) return;

      let group = ordersMap.get(role.order_id);

      if (!group) {
        group = {
          orderId: role.order_id,
          orderNumber:
            role.order?.order_number ||
            `ORD-${role.order_id.split('-')[0].toUpperCase()}`,
          rolesCount: 0,
          roles: [],
        };
        ordersMap.set(role.order_id, group);
      }

      group.rolesCount++;
      group.roles.push(role);
    });

    return Array.from(ordersMap.values());
  }

  // ==========================================
  // TRIGGERS DE MODALES
  // ==========================================
  public openOrderModal(): void {
    if (this.rolesToProcess().length > 0) {
      this.showOrderModal.set(true);
    }
  }

  public onOrderSuccessfullyCreated(res: PapOrderResponse): void {
    this.showOrderModal.set(false);
    this.rolesToProcess.set([]);
    this.initializeWorkflow();

    // 🟢 ¡Magia Reactiva! Avisamos al Orquestador para que actualice el Dashboard
    if (res.phase_actual && res.progress_percentage !== undefined) {
      this.phaseClosed.emit({
        req_id: this.requirementId(),
        phase_actual: res.phase_actual,
        progreso_global: res.progress_percentage,
      });
    }
  }

  public openResultModal(
    orderId: string | null,
    mode: 'create' | 'view' = 'create',
  ): void {
    if (!orderId) return;

    const allRoles = this.store.roles();

    const rolesInOrder = allRoles.filter(
      (r) => r.order_id === orderId || r.order?.id === orderId,
    );

    if (rolesInOrder.length > 0) {
      this.activeOrderId.set(orderId);
      const realOrderNumber =
        rolesInOrder[0].order?.order_number ||
        `ORD-${orderId.split('-')[0].toUpperCase()}`;

      this.activeOrderNumber.set(realOrderNumber);

      this.activeRolesInOrder.set(rolesInOrder);

      this.isResultViewMode.set(mode === 'view');
      this.showResultModal.set(true);
    }
  }

  public onResultSuccessfullyRegistered(): void {
    this.showResultModal.set(false);
    this.initializeWorkflow();
  }
  /**
   * Abre el modal de dictamen en modo lectura reconstruyendo la historia (Sin 'any')
   */
  public openSnapshot(order: SuccessfulOrder): void {
    if (!order) return;

    // 🟢 Triple filtro infalible usando las propiedades del objeto order
    const rolesForOrder = this.store
      .roles()
      .filter(
        (r) =>
          r.order_id === order.id ||
          r.order?.id === order.id ||
          r.order?.order_number === order.order_number,
      );

    // Asignamos las señales
    this.activeOrderId.set(order.id);
    this.activeOrderNumber.set(order.order_number);
    this.activeRolesInOrder.set(rolesForOrder);
    this.isResultViewMode.set(true);
    this.showResultModal.set(true);
  }

  /**
   * Cierra el modal de resultados
   */
  public closeResultModal(): void {
    this.showResultModal.set(false);
    this.isResultViewMode.set(false);
    this.activeOrderId.set('');
    this.activeOrderNumber.set('');
    this.activeRolesInOrder.set([]);
  }

  // ==========================================
  // CIERRE GLOBAL DE FASE (DELEGANDO SEGURIDAD A LARAVEL)
  // ==========================================
  public async finalizePapPhase(): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      '¿Cerrar Fase de Pase a Producción?',
      'Al confirmar, el requerimiento mutará a CERRADO_HISTÓRICO y la base de datos quedará bloqueada.',
    );

    // Patrón Early Return: si cancela el modal, salimos.
    if (!isConfirmed) return;

    this.isSubmitting.set(true); // Bloqueamos el botón y mostramos spinner

    this.papApiService.closePhase(this.requirementId()).subscribe({
      next: (response) => {
        this.isSubmitting.set(false);
        this.notificationService.showSuccess(
          '¡Fase Sellada!',
          'El despliegue en Pase a Producción ha concluido exitosamente.',
        );

        // Emitimos la respuesta al padre (Orquestador)
        this.phaseClosed.emit({
          req_id: this.requirementId(),
          phase_actual: 'PAP-C', // Ajusta si tu estatus final es RC o CERRADO_HISTORICO
          progreso_global: response.progress_percentage || 100,
        });

        // Refrescamos la vista local por si acaso
        this.initializeWorkflow();
      },
      error: (err: HttpErrorResponse) => {
        this.isSubmitting.set(false);
        console.error('Error cerrando la fase PAP:', err.message, err.error);

        // PROTECCIÓN UX: Manejo dinámico del error enviado por Laravel
        let errorMessage = 'No se pudo cerrar la fase de Pase a Producción.';

        if (err.status >= 500) {
          errorMessage =
            'Ocurrió un error interno en el servidor. Por favor, contacte a soporte.';
        } else if (err.error?.message) {
          // Capturamos el mensaje del backend
          errorMessage = err.error.message;
        }

        // Lanzamos el SweetAlert adecuado según el código HTTP
        if (err.status === 422 || err.status === 403) {
          this.notificationService.showWarning('Cierre Denegado', errorMessage);
        } else {
          this.notificationService.showError('Operación Fallida', errorMessage);
        }
      },
    });
  }
}
