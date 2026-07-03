
/**
 * Interfaces de Dominio para el Ecosistema de Requerimientos (ECOSGRTI)
 * Mapean exactamente las estructuras de datos devueltas por PostgreSQL/Redis.
 */

export interface RequirementDashboard {
  id: string;
  rrti: string;
  requirement_type: string;
  creation_date: string;
  description: string;
  management_type: string;
  status: string;
  is_locked: boolean;
  cspe_consultants: unknown[];
  first_name: string;
  last_name: string;
  snapshot_unit_name: string; 
}

export interface RequirementDetail {
  id: string;
  rrti: string;
  requirement_type: string;
  management_type: string;
  creation_date: string;
  description: string;
  functional_consultant_id: string; 
  status: string;
  is_locked: boolean;
  
  it_request_doc_path?: string;
  needs_spreadsheet_path?: string;

  snapshot_society_name?: string;
  snapshot_system_name?: string;
  snapshot_unit_name?: string;

  cspe_consultants?: RequirementCspeRelation[];
  functional_consultant?: {
    person_id: string;
  };
}

export interface OrganizationalGraph {
  persona_id: string;
  functional_consultant_id: string;
  requesting_unit_name: string;
  system_name: string;
  society_name: string;
}

export interface CatalogItem {
  id: string;
  name: string;
}

export interface FunctionalConsultantItem {
  id: string;
  full_name: string;
  persona_id: string;
  functional_consultant_id: string;

  // 🟢 Propiedades para la jerarquía (opcionales por si no siempre vienen)
  society_name?: string;
  system_name?: string;
  unit_name?: string;

  // Si tu backend lo envía como objetos anidados (Eager Loading):
  society?: { name: string };
  system?: { name: string };
  requesting_unit?: { name: string };
  
}

export interface CspeConsultantItem {
  cspe_id: string;
  full_name: string;
}

export interface RequirementCspeRelation {
  id: string;
  pivot?: {
    cspe_consultant_id: string;
    requirement_id?: string;
  };
}

// Interfaz para la respuesta del Ticket de Borrado (US24)
export interface DeletionTicketResponse {
  success: boolean;
  message: string;
  data: {
    deletion_ticket: string;
    expires_in_seconds: number;
  };
}

// estimation.interface.ts
export interface EstimationPhase {
  phase_name: string;
  start_date: string; // Formato YYYY-MM-DD
  end_date: string;   // Formato YYYY-MM-DD
  estimated_hours: number;
}

export interface EstimationPayload {
  requirement_id: string;
  phases: EstimationPhase[];
}

export interface SavedEstimatedPhase {
  phase_name: string;
  start_date: string; // Formato YYYY-MM-DD
  end_date: string;   // Formato YYYY-MM-DD
  estimated_hours: number;
}

export interface SavedEstimation {
  id: string;
  estimated_phases: SavedEstimatedPhase[];
}

export interface EstimationDetailsData {
  id: string;
  rrti: string;
  status: string;
  is_locked: boolean;
  estimation: SavedEstimation | null; // null si aún no se ha guardado borrador
}

export interface EstimationDetailsResponse {
  success: boolean;
  data: EstimationDetailsData;
}