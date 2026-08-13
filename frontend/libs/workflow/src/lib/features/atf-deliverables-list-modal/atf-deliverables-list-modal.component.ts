// libs/workflow/src/lib/features/atf-deliverables-list/atf-deliverables-list.component.ts

import { Component, computed, inject, input, output, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DeliverableService } from '../../data-access/services/deliverable.service';
import { NotificationService } from '../../data-access/services/notification.services';
import { Deliverable } from '../../data-access/models/deliverable.model';
import { DeliverableFormModalComponent } from '../atf-deliverables-form-modal/atf-deliverables-form-modal.component';

@Component({
  selector: 'lib-atf-deliverables-list-modal',
  standalone: true,
  imports: [CommonModule, FormsModule, DeliverableFormModalComponent],
  templateUrl: './atf-deliverables-list-modal.component.html',
  styleUrls: ['./atf-deliverables-list-modal.component.scss']
})
export class AtfDeliverablesListComponent implements OnInit {
  // Inputs requeridos y configuración de fase
  public requirementId = input.required<string>();
  public requirementNumber = input.required<string>(); // Para mostrar en modo lectura
  public isPhaseClosed = input<boolean>(false);

  // Output para regresar a la vista anterior
  public volver = output<void>();
  

  // Inyección de dependencias
  private deliverableService = inject(DeliverableService);
  private notificationService = inject(NotificationService);

  // Signals para el estado del componente
  public deliverables = signal<Deliverable[]>([]);
  public isLoading = signal<boolean>(true);
  public searchTerm = signal<string>('');
  
  // Signals para el manejo del modal
  public isModalOpen = signal<boolean>(false);
  public selectedDeliverable = signal<Deliverable | null>(null);
  

  // Computed Signal para el filtrado dinámico (CU-024)
  public filteredDeliverables = computed(() => {
    const term = this.searchTerm().toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    if (!term) return this.deliverables();
    
    return this.deliverables().filter(deliverable => {
      const normalizedName = deliverable.name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      return normalizedName.includes(term);
    });
  });

  ngOnInit(): void {
    this.loadDeliverables();
  }

  /**
   * Carga los entregables asociados al requerimiento (CU-022)
   */
  public loadDeliverables(): void {
    this.isLoading.set(true);
    this.deliverableService.getDeliverables(this.requirementId()).subscribe({
      next: (response) => {
        this.deliverables.set(response.data);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.notificationService.showError('Error', 'No se pudieron cargar los entregables.');
        console.error('Error cargando entregables', err);
        this.isLoading.set(false);
        this.deliverables.set([]);
      }
    });
  }

  /**
   * Actualiza el término de búsqueda de forma reactiva
   */
  public updateSearch(term: string): void {
    this.searchTerm.set(term);
  }

  // --- MÉTODOS PARA GESTIÓN DEL MODAL ---

  public openCreateModal(): void {
    this.selectedDeliverable.set(null);
    this.isModalOpen.set(true);
  }

  public openViewModal(deliverable: Deliverable): void {
    this.selectedDeliverable.set(deliverable);
    this.isModalOpen.set(true);
  }

  public closeModal(): void {
    this.isModalOpen.set(false);
    this.selectedDeliverable.set(null);
  }

  /**
   * Listener que se dispara cuando el modal emite un guardado exitoso
   */
  public onDeliverableSaved(): void {
    this.loadDeliverables();
    this.deliverableService.refreshDashboard$.next(); // Sincroniza avance global
  }

  public handleClose(): void {
    console.log('Cerrando modal de entregables...');
    this.volver.emit();
  }

  public async onDelete(deliverable: Deliverable): Promise<void> {
    // 1. Invocamos el modal de confirmación unificado
    const isConfirmed = await this.notificationService.confirm(
      '¿Está seguro?', 
      `Esta acción eliminará el entregable: ${deliverable.name} de forma permanente.`
    );

    // 2. Evaluamos la respuesta asíncrona
    if (isConfirmed) {
      this.deliverableService.deleteDeliverable(deliverable.id).subscribe({
        next: (response) => {
          // 3. Notificación de éxito
          this.notificationService.showSuccess(
            'Operación Exitosa', 
            response.message || 'Entregable eliminado correctamente'
          );
          
          // 4. Actualizamos el estado del dashboard
          this.loadDeliverables();
          this.deliverableService.refreshDeliverables$.next(); 
        },
        error: (err) => {
          // 5. Notificación de error
          const errorMsg = err.error?.message || 'No se pudo eliminar el entregable. Intente de nuevo.';
          this.notificationService.showError('Error de Procesamiento', errorMsg);
          console.error('Error al eliminar', err);
        }
      });
    }
  }
}