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
  consultor_funcional: string;
  unidad_solicitante: string;
  cor_closed_roles_count?: number;
  pi_closed_roles_count?: number;
  coe_closed_deliverables_count?: number;
}