import { Component, input, output, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RequirementDetail } from '../../data-access/models/requirement.model';

export type PhaseState = 'COMPLETED' | 'ACTIVE' | 'PENDING';

interface MapNode {
  code: string;
  name: string;
  state: PhaseState;
  icon: string;
}

@Component({
  selector: 'lib-requirement-map-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './requirement-map-modal.component.html',
  styleUrls: ['./requirement-map-modal.component.scss']
})
export class RequirementMapModalComponent {
  // Inputs & Outputs
  requirement = input.required<RequirementDetail>(); 
  closeModal = output<void>();

  // Rutas actualizadas incluyendo Planificación (PL)
  private readonly ROLES_ROUTE = [
    { code: 'PL', name: 'Planificación' },
    { code: 'ATF', name: 'Análisis Técnico Funcional' },
    { code: 'DT', name: 'Diseño Técnico' },
    { code: 'COR', name: 'Construcción de Roles' },
    { code: 'PI', name: 'Pruebas Integrales' },
    { code: 'CER', name: 'Certificación de Roles' },
    { code: 'PAP', name: 'Pase a Producción' },
    { code: 'AU', name: 'Asignación de Usuario' }
  ];

  private readonly DELIVERABLES_ROUTE = [
    { code: 'PL', name: 'Planificación' },
    { code: 'ATF', name: 'Análisis Técnico Funcional' },
    { code: 'COE', name: 'Construcción de Entregables' },
    { code: 'CEE', name: 'Certificación de Entregables' }
  ];

  // =========================================================================
  // COMPUTADOS: Generación Dinámica del Grafo
  // =========================================================================

  rolesPath = computed<MapNode[]>(() => {
    const req = this.requirement();
    if (req.management_type === 'Entregables') return []; // No aplica
    return this.calculateNodeStates(this.ROLES_ROUTE, req);
  });

  deliverablesPath = computed<MapNode[]>(() => {
    const req = this.requirement();
    if (req.management_type === 'Roles') return []; // No aplica
    return this.calculateNodeStates(this.DELIVERABLES_ROUTE, req);
  });

  isMixed = computed(() => this.requirement().management_type === 'Mixto');

  // =========================================================================
  // LÓGICA DE ESTADOS (SOPORTE PARA PARALELISMO)
  // =========================================================================

  // private calculateNodeStates(route: {code: string, name: string}[], req: RequirementDetail): MapNode[] {
  //   const frozenPhases = req.frozen_phases || [];
  //   const openPhases = req.open_phases || []; 

  //   return route.map(phase => {
  //     // CASO ESPECIAL: Planificación (PL
  //     if (phase.code === 'PL') {
  //       const isCompleted = req.status !== 'RC' || frozenPhases.length > 0;
  //       const isActive = req.status === 'RC';

  //       if (isCompleted) {
  //         return { ...phase, state: 'COMPLETED', icon: 'fa-solid fa-circle-check text-success' };
  //       }
  //       if (isActive) {
  //         return { ...phase, state: 'ACTIVE', icon: 'fa-solid fa-circle-dot text-primary fa-fade' };
  //       }
  //       return { ...phase, state: 'PENDING', icon: 'fa-regular fa-circle text-muted' };
  //     }
  //     // 🟢 CASO GENERAL: Fases de Vanguardia Paralela
  //     // Si está en el arreglo de congeladas, está COMPLETA (Verde)
  //     if (frozenPhases.includes(phase.code)) {
  //       return { ...phase, state: 'COMPLETED', icon: 'fa-solid fa-circle-check text-success' };
  //     }
  //     // Si está en el arreglo de abiertas, está ACTIVA (Azul/Pulse). 
  //     if (openPhases.includes(phase.code)) {
  //       return { ...phase, state: 'ACTIVE', icon: 'fa-solid fa-circle-dot text-primary fa-fade' }; 
  //     }
  //     // Lo que no cumpla lo anterior es FUTURO (Gris)
  //     return { ...phase, state: 'PENDING', icon: 'fa-regular fa-circle text-muted' };
  //   });
  // }

  private calculateNodeStates(route: {code: string, name: string}[], req: RequirementDetail): MapNode[] {
    const frozenPhases = req.frozen_phases || [];
    const openPhases = req.open_phases || []; 
    const isRequirementCreated = req.status === 'RC';

    return route.map(phase => {
      // 🟢 CASO ESPECIAL: Si está recién creado (RC), solo Planificación está Activa y el resto Pendiente
      if (isRequirementCreated) {
        if (phase.code === 'PL') {
          return { ...phase, state: 'ACTIVE', icon: 'fa-solid fa-circle-dot text-primary fa-fade' };
        }
        return { ...phase, state: 'PENDING', icon: 'fa-regular fa-circle text-muted' };
      }

      // CASO ESPECIAL: Planificación (PL) para otros estatus avanzados
      if (phase.code === 'PL') {
        return { ...phase, state: 'COMPLETED', icon: 'fa-solid fa-circle-check text-success' };
      }

      // 🟢 CASO GENERAL: Fases de Vanguardia Paralela
      if (frozenPhases.includes(phase.code)) {
        return { ...phase, state: 'COMPLETED', icon: 'fa-solid fa-circle-check text-success' };
      }
      
      if (openPhases.includes(phase.code)) {
        return { ...phase, state: 'ACTIVE', icon: 'fa-solid fa-circle-dot text-primary fa-fade' }; 
      }

      return { ...phase, state: 'PENDING', icon: 'fa-regular fa-circle text-muted' };
    });
  }
}