import { Injectable, computed, signal } from '@angular/core';
import { PapRole, SuccessfulOrder } from '../models/pap-workflow.model';

@Injectable({
  providedIn: 'root'
})
export class PapRolesStore {
  // ==========================================
  // ESTADO MAESTRO (STATE)
  // ==========================================
  public roles = signal<PapRole[]>([]);
  public searchTerm = signal<string>('');

  // ==========================================
  // SELECCIONES COMPUTADAS (SEGREGACIÓN TRIPARTITA - RN-PAP-4)
  // ==========================================

  // 1. Roles Pendientes por Pasar (Bandeja de Entrada PAP)
  public filteredPending = computed(() => {
    return this.roles()
      .filter(r => r.status === 'PENDING_PAP')
      .filter(r => this.matchesSearch(r));
  });

  // 2. Roles En Proceso (Con Orden de Transporte Activa)
  public filteredInProgress = computed(() => {
    return this.roles()
      .filter(r => r.status === 'IN_PROGRESS')
      .filter(r => this.matchesSearch(r));
  });

  // 3. Roles En Producción (Despliegue Exitoso)
  public filteredInProduction = computed(() => {
    return this.roles()
      .filter(r => r.status === 'IN_PRODUCTION')
      .filter(r => this.matchesSearch(r));
  });


  public filteredClosedOrders = computed(() => {
    return this.roles()
      .filter(r => r.order?.status === 'ORD_CLOSED')
      .filter(r => this.matchesSearch(r));
  });

  
  public successfulOrdersGroups = computed(() => {
    const ordersMap = new Map<string, SuccessfulOrder>();
    const term = this.searchTerm().trim().toLowerCase();

    this.roles().forEach(role => {
      if (role.status === 'IN_PRODUCTION' && role.order) {
        const num = role.order.order_number;
        
        if (!ordersMap.has(num)) {
          ordersMap.set(num, {
            id: role.order.id, // ID intacto para descargar el PDF
            order_number: num,
            roles_count: 1     // Cuenta el primer rol exitoso
          });
        } else {
          const existingOrder = ordersMap.get(num);
          if (existingOrder) {
            existingOrder.roles_count++; // Cuenta los demás roles exitosos
          }
        }
      }
    });
    this.roles().forEach(role => {
      if (role.rejection_history) {
        role.rejection_history.forEach(history => {
          const num = history.order_number;
          // Si la orden existe en nuestro mapa, significa que fue un dictamen mixto.
          if (num && ordersMap.has(num)) {
            const existingOrder = ordersMap.get(num);
            if (existingOrder) {
              existingOrder.roles_count++;
            }
          }
        });
      }
    });

    // Retornamos el array aplicando el filtro del buscador
    return Array.from(ordersMap.values()).filter(o => 
      !term || o.order_number.toLowerCase().includes(term)
    );
  });
  
  public setRoles(roles: PapRole[]): void {
    this.roles.set(roles);
  }

  public updateSearchTerm(term: string): void {
    this.searchTerm.set(term);
  }

  // ==========================================
  // LÓGICA DE BÚSQUEDA INTERNA (RN-PAP-10)
  // ==========================================
  private matchesSearch(role: PapRole): boolean {
    const term = this.searchTerm().trim().toLowerCase();
    if (!term) return true;
    
    // Valida contra el nombre del rol usando optional chaining
    const roleName = role.requirement_role?.role_name?.toLowerCase() || '';
    const orderNumber = (role.order?.order_number || '').toLowerCase();
    
    // Retorna true si el término de búsqueda está incluido en el nombre del rol
    return roleName.includes(term) || orderNumber.includes(term);
  }
  
}