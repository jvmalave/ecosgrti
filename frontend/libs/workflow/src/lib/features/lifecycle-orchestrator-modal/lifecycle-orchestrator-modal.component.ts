import { Component, input, output, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PhaseRolesModalComponent } from '../phase-roles-modal/phase-roles-modal.component';
import { PHASE_CONFIGURATIONS, PhaseConfig } from '../../data-access/models/phase-config.interface';
import { DashboardRequirement } from '../../data-access/models/lifecycle-orchestrator.model';
import { CerRolesComponent } from '../../features/cer-roles/cer-roles.component';
import { CeeDeliverablesComponent } from '../../features/cee-deliverables/cee-deliverables.component';
import { PapRolesComponent } from '../../features/pap-roles/pap-roles.component';
import { AuRolesComponent } from '../../features/au-roles/au-roles.component';



export type PhaseAction = 'DT' | 'COR' | 'COE' | 'CER' | 'CEE' | 'PI' | 'PAP' | 'AU';

@Component({
  selector: 'lib-lifecycle-orchestrator-modal',
  standalone: true,
  imports: [
    CommonModule, 
    PhaseRolesModalComponent, 
    CerRolesComponent,
    CeeDeliverablesComponent,
    PapRolesComponent,
    AuRolesComponent
  ],
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

  // Habilita COR solo si hay roles cerrados en DT
  isCoREnabled = computed(() => {
    // Extrae el valor, asegurando que sea un número (fallback a 0)
    const closedDtRoles = this.req().dt_closed_roles_count || 0;
    
    console.log('Auditoría Hard-Gate DT -> Roles cerrados en ATF:', closedDtRoles);
    
    // El botón solo se habilita si hay al menos 1 rol en estado CLOSED en DT
    return Number(closedDtRoles) > 0;
  });

  isCoEEnabled = computed(() => {
    const req = this.req();
    if (!req) return false;

    // Lógica de Lista Negra: NO se habilita en fases prematuras
    const invalidStatuses = ['RC', 'EST', 'ATF-I'];
    const isValidStatus = !invalidStatuses.includes(req.status);
    
    const deliverablesCount = req.deliverables_count || 0; 

    console.log('Auditoría Hard-Gate COE -> Entregables cerrados en ATF:', deliverablesCount);
    
    return isValidStatus && (deliverablesCount > 0);
  });


  isPiEnabled = computed(() => {
    const PiReq = this.req();
    if (!PiReq) return false;

    const invalidStatuses = ['RC', 'EST', 'ATF-I', 'DT-I', 'COE-I'];
    const isValidStatus = !invalidStatuses.includes(PiReq.status);

    const hasClosedCorRoles = (PiReq.cor_closed_roles_count ?? 0) > 0;
    const hasClosedCorCount = (PiReq.dt_closed_roles_count ?? 0);

    console.log('Auditoría Hard-Gate Pi -> Roles cerrados en COR:', hasClosedCorCount);

    return isValidStatus && hasClosedCorRoles;
  });

  isCeREnabled = computed(() => {
    const req = this.req();
    if (!req) return false;

    // Bloquea la apertura si el requerimiento está en fases muy tempranas
    const invalidStatuses = ['RC', 'EST', 'ATF-I', 'DT-I', 'COE-I',];
    const isValidStatus = !invalidStatuses.includes(req.status);

    // Verifica si hay roles que hayan superado la fase de Pruebas Integrales (PI)
    const hasClosedPiRoles = (req.pi_closed_roles_count ?? 0) > 0;

    console.log('Auditoría Hard-Gate CER -> Roles cerrados en PI:', req.pi_closed_roles_count);

    return isValidStatus && hasClosedPiRoles;
  });

  isCeEEnabled = computed(() => {
    const req = this.req();
    if (!req) return false;

    // Lógica de Lista Negra: NO se habilita en fases prematuras
    const invalidStatuses = ['RC', 'EST', 'ATF-I', 'ATF-C', 'DT-I', 'DT-C', 'COR-I', 'COR-C', 'COE-I'];
    const isValidStatus = !invalidStatuses.includes(req.status);

    // HARD-GATE: Verifica si hay entregables que hayan superado la fase de Construcción (COE)
    const hasClosedCoEDeliverables = (req.coe_closed_deliverables_count ?? 0) > 0;

    console.log('Auditoría Hard-Gate CEE -> Entregables cerrados en COE:', req.coe_closed_deliverables_count);

    return isValidStatus && hasClosedCoEDeliverables;
  });
  
  isPapEnabled = computed(() => {
    const req = this.req();
    if (!req) return false;

    // Lógica de Lista Negra: NO se habilita en fases prematuras
    const invalidStatuses = ['RC', 'EST', 'ATF-I', 'ATF-C', 'DT-I', 'DT-C', 'COR-I', 'COR-C', 'PI-I', 'PI-C',];
    const isValidStatus = !invalidStatuses.includes(req.status);

    // HARD-GATE: Verifica si hay roles que hayan superado la fase de Certificación (CER)
    // Asumiendo que tu backend devuelve "cer_closed_roles_count"
    const hasCertifiedRoles = (req.cer_closed_roles_count ?? 0) > 0;

    console.log('Auditoría Hard-Gate PAP -> Roles certificados en CER:', req.cer_closed_roles_count);

    return isValidStatus && hasCertifiedRoles;
  });
  isAuEnabled = computed(() => {
    const req = this.req();
    if (!req) return false;

    // Lógica de Lista Negra: NO se habilita en fases prematuras, incluyendo la Fase: Planificación (PL) y Estimación
    const invalidStatuses = ['RC', 'PL', 'EST', 'ATF-I', 'ATF-C', 'DT-I', 'DT-C', 'COR-I', 'COR-C', 'PI-I', 'PI-C', 'CER-I', 'CER-C'];
    const isValidStatus = !invalidStatuses.includes(req.status);

    // HARD-GATE: Verifica si hay roles que hayan superado la fase de Pase a Producción (PAP)
    const hasProductionRoles = (req.pap_closed_roles_count ?? 0) > 0;

    console.log('Auditoría Hard-Gate AU -> Roles en Producción (PAP):', req.pap_closed_roles_count);

    return isValidStatus && hasProductionRoles;
  });


  isCerClosed = computed(() => {
    const req = this.req();
    if (!req) return false;

    // Lista de estatus que indican que CER ya pasó a la historia
    const closedStatuses = ['CER-C', 'PAP-I', 'PAP-C', 'AU-I', 'AU-C', 'FC'];
    
    return closedStatuses.includes(req.status);
  });

  activePhaseConfig = signal<PhaseConfig | null>(null);

  triggerClose(): void {
    this.closeModal.emit();
  }

  // Modifica navigateTo para recibir el objeto de configuración
  navigateTo(config: PhaseConfig): void {
    this.activePhaseConfig.set(config);
    // Emitimos el string extraído de la configuración por si el Dashboard lo está escuchando
    this.openPhase.emit(config.phaseCode as PhaseAction); 
  }

  // Método para que el modal hijo pueda avisarle al orquestador que se cerró
  closeActivePhase(): void {
    this.activePhaseConfig.set(null);
  }
  // Output para reenviar la data al componente padre (Dashboard)
  public requirementUpdated = output<{req_id: string, phase_actual: string, progreso_global: number}>();

  // Función para reenviar la data al componente padre
  public onPhaseStatusChanged(data: {req_id: string, phase_actual: string, progreso_global: number}): void {
    // Reenviamos el evento hacia el Dashboard padre, el cual sí posee el Store global
    this.requirementUpdated.emit(data);
  }

  /**
   * Método disparado por los modales hijos (ej. AU) 
   * para forzar la recarga reactiva del Dashboard sin necesidad de F5.
   */
  public refreshDashboardData(): void {
    console.log('🔄 Forzando recarga reactiva del Dashboard desde el Orquestador...');
    
    const currentReq = this.req();
    
    if (currentReq) {
      // Reenvia el evento hacia el Dashboard padre
      this.requirementUpdated.emit({
        req_id: currentReq.id,
        phase_actual: currentReq.status,
        // Usamos el progreso actual, el Dashboard al recargar traerá el nuevo 
        progreso_global: (currentReq as DashboardRequirement).progress_percentage ?? 0 
      });
    }
  }
}