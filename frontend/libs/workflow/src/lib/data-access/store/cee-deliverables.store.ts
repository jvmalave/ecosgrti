import { Injectable, signal, computed } from '@angular/core';
import { CeeDeliverable } from '../models/cee-workflow.model';

@Injectable({ providedIn: 'root' })
export class CeeDeliverablesStore {
  // Estado Maestro (Fuente de Verdad Única)
  public readonly deliverables = signal<CeeDeliverable[]>([]);
  public readonly searchTerm = signal<string>('');

  // Segregación Tripartita Reactiva
  public readonly pendingDeliverables = computed(() => 
    this.deliverables().filter(d => d.status === 'PENDING_CERTIFICATION')
  );
  public readonly inProgressDeliverables = computed(() => 
    this.deliverables().filter(d => d.status === 'IN_PROGRESS')
  );
  public readonly certifiedDeliverables = computed(() => 
    this.deliverables().filter(d => d.status === 'CERTIFIED')
  );

  // Filtrado Local Client-Side con Normalización Léxica
  public readonly filteredPending = computed(() => 
    this.filterByTerm(this.pendingDeliverables(), this.searchTerm())
  );
  public readonly filteredInProgress = computed(() => 
    this.filterByTerm(this.inProgressDeliverables(), this.searchTerm())
  );
  public readonly filteredCertified = computed(() => 
    this.filterByTerm(this.certifiedDeliverables(), this.searchTerm())
  );

  // Puertas Lógicas (Hard Gates Frontend para habilitar botones)
  public readonly canClosePhase = computed(() => {
    const total = this.deliverables().length;
    const certified = this.certifiedDeliverables().length;
    return total > 0 && total === certified;
  });

  // Mutadores de Estado
  public setDeliverables(newDeliverables: CeeDeliverable[]): void {
    this.deliverables.set(newDeliverables);
  }

  public updateSearchTerm(term: string): void {
    this.searchTerm.set(term);
  }

  /**
   * Sincroniza el estado local tras una transacción exitosa con el backend
   * Mueve dinámicamente los entregables entre las tres tablas sin recargar la página.
   */
  public updateDeliverablesStatusLocally(deliverableIds: string[], newStatus: 'PENDING_CERTIFICATION' | 'IN_PROGRESS' | 'CERTIFIED', ticketId?: string): void {
    this.deliverables.update(currentDeliverables => 
      currentDeliverables.map(deliverable => 
        deliverableIds.includes(deliverable.id) 
          ? { ...deliverable, status: newStatus, ticket_id: ticketId || deliverable.ticket_id } 
          : deliverable
      )
    );
  }

  /**
   * Algoritmo de normalización (NFD) para búsqueda universal de subcadenas.
   */
  private filterByTerm(items: CeeDeliverable[], term: string): CeeDeliverable[] {
    if (!term.trim()) return items;
    
    const normalizedTerm = term.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    
    return items.filter(item => {
      // Buscar por nombre del entregable
      const deliverableName = item.deliverable?.name || '';
      const normalizedDeliverableName = deliverableName.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      
      // Buscar por número de ticket (si lo tiene asignado)
      const ticketNumber = item.ticket?.ticket_number || '';
      const normalizedTicket = ticketNumber.toLowerCase();

      return normalizedDeliverableName.includes(normalizedTerm) || normalizedTicket.includes(normalizedTerm);
    });
  }
}