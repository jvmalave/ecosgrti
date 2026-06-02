
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
  persona_id: string;
  full_name: string;
}

export interface CspeConsultantItem {
  cspe_id: string;
  full_name: string;
}