import { Component, input, output, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PhaseRolesModalComponent } from '../phase-roles-modal/phase-roles-modal.component';
import { PHASE_CONFIGURATIONS, PhaseConfig } from '../../data-access/models/phase-config.interface';

export interface DashboardRequirement {
  id: string;
  rrti: string;
  management_type: string;
  status: string;
  roles_count: number;
  has_roles: boolean | number | string;
  dt_closed_roles_count?: number;
  deliverables_count?: number;
  frozen_phases: string[];
}

export type PhaseAction = 'DT' | 'COR' | 'COE' | 'CER' | 'CEE' | 'PI' | 'PAP' | 'AU';

@Component({
  selector: 'lib-lifecycle-orchestrator-modal',
  standalone: true,
  imports: [CommonModule, PhaseRolesModalComponent],
  templateUrl: './lifecycle-orchestrator-modal.component.html',
  styleUrls: ['./lifecycle-orchestrator-modal.component.scss']
})
export class LifecycleOrchestratorModalComponent {
  
  req = input.required<DashboardRequirement>();
  closeModal = output<void>();
  openPhase = output<PhaseAction>();

  
  readonly PHASES = PHASE_CONFIGURATIONS;

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
  
  isDtEnabled = computed(() => {
    const count = this.req().roles_count;
    console.log('Auditoría Hard-Gate DT -> roles_count:', count, 'Tipo:', typeof count);
    return Number(count) > 0;
  });

  // Habilitar COR solo si hay roles cerrados en DT
  isCoREnabled = computed(() => {
    // Extraemos el valor, asegurando que sea un número (fallback a 0)
    const closedDtRoles = this.req().dt_closed_roles_count || 0;
    
    console.log('Auditoría Hard-Gate COR -> Roles cerrados en DT:', closedDtRoles);
    
    // El botón solo se habilita si hay al menos 1 rol en estado CLOSED en DT
    return Number(closedDtRoles) > 0;
  });

  isCoEEnabled = computed(() => {
    const req = this.req();
    if (!req) return false;

    // 🟢 Lógica de Lista Negra: NO se habilita en fases prematuras
    const invalidStatuses = ['RC', 'EST', 'ATF-I'];
    const isValidStatus = !invalidStatuses.includes(req.status);
    
    const deliverablesCount = req.deliverables_count || 0; 
    
    return isValidStatus && (deliverablesCount > 0);
  });
  
  isCeREnabled = computed(() => false);
  isCeEEnabled = computed(() => false);
  isPiEnabled = computed(() => false);
  isPapEnabled = computed(() => false);
  isAuEnabled = computed(() => false);

  // 5. CAMBIO CLAVE: Esta señal ya no guarda un string ('DT'), sino que guarda el objeto PhaseConfig completo
  activePhaseConfig = signal<PhaseConfig | null>(null);

  triggerClose(): void {
    this.closeModal.emit();
  }

  // 6. Modificamos navigateTo para recibir el objeto de configuración
  navigateTo(config: PhaseConfig): void {
    this.activePhaseConfig.set(config);
    // Emitimos el string extraído de la configuración por si el Dashboard lo está escuchando
    this.openPhase.emit(config.phaseCode as PhaseAction); 
  }

  // 7. Nuevo método para que el modal hijo pueda avisarle al orquestador que se cerró
  closeActivePhase(): void {
    this.activePhaseConfig.set(null);
  }
}