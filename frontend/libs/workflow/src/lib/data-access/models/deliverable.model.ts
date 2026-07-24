// Modelo de un entregable
export interface Deliverable {
  id: string;
  requirement_id: string;
  name: string;
  description: string;
  created_at?: string;
  updated_at?: string;
}

// Respuesta para un único entregable (ej. creación o actualización)
export interface DeliverableResponse {
  message: string;
  data: Deliverable;
}

// Respuesta para el listado (CU-022)
export interface DeliverablesListResponse {
  message: string;
  data: Deliverable[];
}

// Respuesta genérica para acciones sin data de retorno (ej. eliminación)
export interface ActionResponse {
  message: string;
}

