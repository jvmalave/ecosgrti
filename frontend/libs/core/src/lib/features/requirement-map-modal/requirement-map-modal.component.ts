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

  // Definición dura de las rutas
  private readonly ROLES_ROUTE = [
    { code: 'ATF', name: 'Análisis Técnico Funcional' },
    { code: 'DT', name: 'Diseño Técnico' },
    { code: 'COR', name: 'Construcción de Roles' },
    { code: 'PI', name: 'Pruebas Integrales' },
    { code: 'CER', name: 'Certificación de Roles' },
    { code: 'PAP', name: 'Pase a Producción.' },
    { code: 'AU', name: 'Asignacion de Usuario' }
  ];

  private readonly DELIVERABLES_ROUTE = [
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
    return this.calculateNodeStates(this.ROLES_ROUTE, req.frozen_phases || []);
  });

  deliverablesPath = computed<MapNode[]>(() => {
    const req = this.requirement();
    if (req.management_type === 'Roles') return []; // No aplica
    return this.calculateNodeStates(this.DELIVERABLES_ROUTE, req.frozen_phases || []);
  });

  isMixed = computed(() => this.requirement().management_type === 'Mixto');

  // =========================================================================
  // LÓGICA DE ESTADOS
  // =========================================================================

  private calculateNodeStates(route: {code: string, name: string}[], frozenPhases: string[]): MapNode[] {
    let foundActive = false;

    return route.map(phase => {
      // 1. Si está en el arreglo de congeladas, está COMPLETA (Verde)
      if (frozenPhases.includes(phase.code)) {
        return { ...phase, state: 'COMPLETED', icon: 'fa-solid fa-circle-check text-success' };
      }

      // 2. Si no está congelada y no hemos encontrado la activa, esta es la ACTIVA (Azul)
      // (La primera fase de la ruta que NO está congelada es el "Borde de Ataque")
      if (!foundActive) {
        foundActive = true;
        return { ...phase, state: 'ACTIVE', icon: 'fa-solid fa-circle-dot text-primary fa-fade' }; // fa-fade le da el efecto de pulso
      }

      // 3. Todo lo que esté después de la activa es FUTURO (Gris)
      return { ...phase, state: 'PENDING', icon: 'fa-regular fa-circle text-muted' };
    });
  }
}