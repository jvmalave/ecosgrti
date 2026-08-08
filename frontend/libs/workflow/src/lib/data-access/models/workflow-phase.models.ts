// libs/workflow/src/lib/data-access/models/workflow-phase.models.ts

export interface WorkflowRole {
  id: string;
  requirement_id: string;
  requirement_role_id: string;
  name: string;
  status: 'IN_PROGRESS' | 'CLOSED';
  registers_count?: number;
}

export interface RolesInitResponse {
  requirement_id: string;
  roles_list: WorkflowRole[];
}

export interface RoleStatusUpdateResponse {
  role_id: string;
  new_status: string;
}

export interface PhaseCloseResponse {
  message: string;
  data?: unknown;
}

export interface WorkflowRegister {
  id: string;
  role_id: string;
  title: string;
  date: string;
  description: string;
  created_by?: string;
  updated_by?: string;
  created_at?: string;
  updated_at?: string;
}

export interface WorkflowRegisterPayload {
  title: string;
  date: string;
  description: string;
}

export interface RegistersListResponse {
  id_req: string;
  nombre_rol: string;
  estado_rol: string;
  registros: WorkflowRegister[];
}