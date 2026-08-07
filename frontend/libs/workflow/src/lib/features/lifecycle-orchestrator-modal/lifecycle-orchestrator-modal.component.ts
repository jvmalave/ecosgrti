import { Component, input, output, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DtRolesListModalComponent } from '../dt-roles-list-modal/dt-roles-list-modal.component';

export interface DashboardRequirement {
  id: string;
  rrti: string;
  management_type: string;
  status: string;
  roles_count: number;
  has_roles: boolean | number | string; // Contemplamos variaciones del backend
}

export type PhaseAction = 'DT' | 'COR' | 'COE' | 'CER' | 'CEE' | 'PI' | 'PAP' | 'AU';

@Component({
  selector: 'lib-lifecycle-orchestrator-modal',
  standalone: true,
  imports: [CommonModule, DtRolesListModalComponent],
  templateUrl: './lifecycle-orchestrator-modal.component.html',
  styleUrls: ['./lifecycle-orchestrator-modal.component.scss']
})
export class LifecycleOrchestratorModalComponent {
  
  req = input.required<DashboardRequirement>();
  closeModal = output<void>();
  openPhase = output<PhaseAction>();

  // ==========================================
  // DIMENSIÓN 1: VISIBILIDAD (Tipo de Gestión)
  // ==========================================
  private mgmtType = computed(() => {
    const type = this.req().management_type;
    return type ? type.toUpperCase() : '';
  });

  showDt = computed(() => ['ROLES', 'MIXTO'].includes(this.mgmtType()));
  showCoR = computed(() => ['ROLES', 'MIXTO'].includes(this.mgmtType()));
  showCoE = computed(() => ['ENTREGABLES', 'MIXTO'].includes(this.mgmtType()));
  showCeR = computed(() => ['ROLES', 'MIXTO'].includes(this.mgmtType()));
  showCeE = computed(() => ['ENTREGABLES', 'MIXTO'].includes(this.mgmtType()));
  ShowPi  = computed(() => ['ROLES', 'MIXTO'].includes(this.mgmtType()));
  showPap = computed(() => ['ROLES', 'MIXTO'].includes(this.mgmtType())); 
  showAu = computed(() => ['ROLES', 'MIXTO'].includes(this.mgmtType()));

  // ==========================================
  // DIMENSIÓN 2: ACTIVACIÓN (Hard-Gates)
  // ==========================================
  
  // RN-Requisito de Activación: DT habilitado si la variable es verdadera o mayor a 0


  isDtEnabled = computed(() => {
    const count = this.req().roles_count;
    
    // Imprimimos en la consola del navegador para auditar el dato real
    console.log('Auditoría Hard-Gate DT -> roles_count:', count, 'Tipo:', typeof count);
    
    // Convertimos de forma segura a número y validamos
    return Number(count) > 0;
  });

  

  // Por ahora, cerramos los Hard-Gates de las siguientes fases hasta que 
  // implementemos sus reglas de negocio en los próximos Sprints.
  isCoREnabled = computed(() => false);
  isCoEEnabled = computed(() => false);
  isCeREnabled = computed(() => false);
  isCeEEnabled = computed(() => false);
  isPiEnabled = computed(() => false);
  isPapEnabled = computed(() => false);
  isAuEnabled = computed(() => false);


  activePhaseModal = signal<PhaseAction | null>(null);

  triggerClose(): void {
    this.closeModal.emit();
  }

  navigateTo(phase: PhaseAction): void {
    // Al hacer clic en un botón, activamos el modal correspondiente
    this.activePhaseModal.set(phase);
    
    // Mantenemos la emisión por si el Dashboard necesita registrar el evento
    this.openPhase.emit(phase);
  }
}