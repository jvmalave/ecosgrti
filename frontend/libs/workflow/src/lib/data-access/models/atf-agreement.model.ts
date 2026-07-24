
export interface AtfAgreementPayload {
  description: string;
  agreement_date: string;
}

export interface AtfAgreementResponse {
  // Ajusta estas propiedades según la respuesta exacta de tu backend de Laravel
  message?: string;
  data: {
    id: string;
    requirement_id: string;
    description: string;
    agreement_date: string;
    registered_by_user_id: string;
    created_at: string;
    updated_at: string;
  };
}

export interface AtfAgreementDetail {
  id: string;
  description: string;
  agreement_date: string;
  created_at: string;
}

export interface UpdateManagementTypeResponse {
  message: string;
  tipo_gestion: string;
  progreso_global: number;
}

export interface ClosureReadinessResponse {
  ready: boolean;
  already_closed?: boolean;
  reasons?: string[];
}

export interface ClosureResponse {
  status: string;
  message: string;
  data?: unknown; // Opcional, si deseas mapear la data del requerimiento retornado
}