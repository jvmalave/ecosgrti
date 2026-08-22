// cer-roles.store.ts
import { Injectable, signal, computed } from '@angular/core';
import { CerRole } from '../models/cer-workflow.model';

@Injectable({ providedIn: 'root' })
export class CerRolesStore {
  // Estado Maestro (Fuente de Verdad Única)
  public readonly roles = signal<CerRole[]>([]);
  public readonly searchTerm = signal<string>('');

  // Segregación Tripartita Reactiva (RN-CER-3 y RN-CER-7)
  public readonly pendingRoles = computed(() => 
    this.roles().filter(r => r.status === 'PENDING_CERTIFICATION')
  );
  public readonly inProgressRoles = computed(() => 
    this.roles().filter(r => r.status === 'IN_PROGRESS')
  );
  public readonly certifiedRoles = computed(() => 
    this.roles().filter(r => r.status === 'CERTIFIED')
  );

  // Filtrado Local Client-Side con Normalización Léxica (RN-CER-9 y RN-CER-13)
  public readonly filteredPending = computed(() => 
    this.filterByTerm(this.pendingRoles(), this.searchTerm())
  );
  public readonly filteredInProgress = computed(() => 
    this.filterByTerm(this.inProgressRoles(), this.searchTerm())
  );
  public readonly filteredCertified = computed(() => 
    this.filterByTerm(this.certifiedRoles(), this.searchTerm())
  );

  // Puertas Lógicas (Hard Gates Frontend para habilitar botones)
  public readonly canClosePhase = computed(() => {
    const total = this.roles().length;
    const certified = this.certifiedRoles().length;
    return total > 0 && total === certified;
  });

  // Mutadores de Estado
  public setRoles(newRoles: CerRole[]): void {
    this.roles.set(newRoles);
  }

  public updateSearchTerm(term: string): void {
    this.searchTerm.set(term);
  }

  /**
   * Sincroniza el estado local tras una transacción exitosa con el backend
   * Mueve dinámicamente los roles entre las tres tablas sin recargar la página.
   */
  public updateRolesStatusLocally(roleIds: string[], newStatus: 'PENDING_CERTIFICATION' | 'IN_PROGRESS' | 'CERTIFIED', ticketId?: string): void {
    this.roles.update(currentRoles => 
      currentRoles.map(role => 
        roleIds.includes(role.id) 
          ? { ...role, status: newStatus, ticket_id: ticketId || role.ticket_id } 
          : role
      )
    );
  }

  /**
   * Algoritmo de normalización (NFD) para búsqueda universal de subcadenas.
   * Permite que "administrador", "Administrador" o "administradór" coincidan.
   */
  private filterByTerm(items: CerRole[], term: string): CerRole[] {
    if (!term.trim()) return items;
    
    const normalizedTerm = term.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    
    return items.filter(item => {
      const roleName = item.requirement_role?.role_name || '';
      const normalizedRoleName = roleName.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      return normalizedRoleName.includes(normalizedTerm);
    });
  }
}