// =========================================================================
// ENTIDADES BASE Y PAYLOADS GENÉRICOS
// =========================================================================

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
 * Representa el payload genérico para guardar o actualizar bitácoras
 */
export interface WorkflowRegisterPayload {
  title: string;
  date: string;
  description: string;
}


// =========================================================================
// RESPUESTAS DE ESTADOS Y CICLO DE VIDA (FASES)
// =========================================================================

/**
 * Respuesta del endpoint de inicialización (roles-init / deliverables-init)
 */
export interface RolesInitResponse {
  requirement_id: string;
  roles_list: WorkflowPhaseItem[]; 
}

export interface RoleStatusUpdateResponse {
  new_status: string;
}

export interface PhaseCloseResponse {
  message: string;
}


// =========================================================================
// RESPUESTAS DE BITÁCORAS (REGISTROS / ACTIVIDADES)
// =========================================================================

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

// =========================================================================
// ENTIDADES DE FASE PI (PRUEBAS INTEGRALES - USUARIOS DE PRUEBA)
// =========================================================================

export interface PiTestUserItem {
  id: string;
  requirement_id: string;
  pi_role_id: string;
  identifier: string;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string | null;
}

export interface PiTestUserListResponse {
  data: PiTestUserItem[];
}

export interface PiTestUserActionResponse {
  message: string;
  data: PiTestUserItem;
}

export interface PiTestUserDeleteResponse {
  message: string;
}

export interface PiTestUserPayload {
  requirement_id: string;
  identifier: string;
  force: boolean;
}

// =========================================================================
// ENTIDADES DE FASE PI (APROBACIÓN FUNCIONAL)
// =========================================================================

export interface PiFunctionalApprovalItem {
  id: string;
  pi_role_id: string;
  file_path: string;
  file_size: number;
  original_name: string;
  created_by?: string;
  updated_by?: string | null;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string | null;
}

export interface PiFunctionalApprovalResponse {
  message: string;
  data: PiFunctionalApprovalItem;
}