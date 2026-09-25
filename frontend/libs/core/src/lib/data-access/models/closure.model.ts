export interface ClosureRequirementData {
  id: string;
  rrti: string;
  creation_date: string;
}

// Tipado estricto para la respuesta del Borrador (Etapa 1)
export interface ClosureDraftResponse {
  message: string;
  draft_pdf: string; // Documento codificado en Base64 listo para renderizar
}

// Tipado estricto para la Transacción Final (Etapa 2)
export interface ClosureFinalResponse {
  status: string;
  progreso_global: number;
  message: string;
}