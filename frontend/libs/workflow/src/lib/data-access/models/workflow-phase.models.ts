// libs/workflow/src/lib/data-access/models/workflow-phase.models.ts

/**
 * Representa la transacción individual en la bitácora (Registro o Actividad)
 */
export interface WorkflowRecord {
  id: string;
  title: string;
  date: string;
  description: string;
  created_at?: string;
}

/**
 * Representa un elemento padre (Rol o Entregable) devuelto por la inicialización.
 * Combina los atributos posibles de COR y COE usando propiedades opcionales.
 */

export interface WorkflowPhaseItem {
  id: string;
  status: string; // Puede ser 'IN_PROGRESS', 'CLOSED', 'PENDING_CERTIFICATION', 'CERTIFIED'
  // Propiedades exclusivas de COR / PI / CER
  name?: string;
  requirement_id?: string;
  requirement_role_id?: string;
  is_approved?: boolean; //  US32: Bandera para la Aprobación Funcional en PI
  // Propiedades exclusivas de COE / CEE
  req_id?: string;
  deliverable_id?: string;
  master_deliverable?: {
    id: string;
    name: string;
  };
  // Propiedad inyectada por withCount() en Laravel
  registers_count?: number; 
  // Propiedad inyectada para CEE/CER relacionada a los tickets
  ticket_id?: string; 
}

/**
 * Respuesta del endpoint de inicialización (roles-init / deliverables-init)
 */
export interface RolesInitResponse {
  requirement_id: string;
  roles_list: WorkflowPhaseItem[]; 
}

/**
 * Respuesta cruda (Raw) del backend al consultar la bitácora de un componente.
 */
export interface RawRegistersResponse {
  id_req: string;
  // Atributos si la respuesta viene de COR
  nombre_rol?: string;
  estado_rol?: string;
  registros?: WorkflowRecord[];
  // Atributos si la respuesta viene de COE
  nombre_entregable?: string;
  estado_entregable?: string;
  actividades?: WorkflowRecord[];
}

/**
 * Representa la respuesta unificada que utilizará el componente visual HTML
 */
export interface WorkflowRegistersResponse {
  reqId: string;
  parentName: string; 
  parentStatus: string; 
  records: WorkflowRecord[]; 
}

/**
 * Representa el payload genérico para guardar o actualizar
 */
export interface WorkflowRegisterPayload {
  title: string;
  date: string;
  description: string;
}

export interface RoleStatusUpdateResponse {
  new_status: string;
}

export interface PhaseCloseResponse {
  message: string;
}

