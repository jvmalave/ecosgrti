
// 🚀 1. Modelo Principal del Dominio
export interface RequirementRole {
  id: string;
  requirement_id: string;
  role_name: string;
  description: string;
  assignment_type: string;
}

//  Interfaces estrictas para las respuestas de la API 
export interface ActionResponse {
  message: string;
}

export interface RoleResponse extends ActionResponse {
  data: RequirementRole;
}

export interface RolesListResponse {
  data: RequirementRole[];
}