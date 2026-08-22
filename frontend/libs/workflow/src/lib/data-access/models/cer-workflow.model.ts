// cer-workflow.model.ts

export type CertStatus = 'PENDING_CERTIFICATION' | 'IN_PROGRESS' | 'CERTIFIED';
export type TicketStatus = 'TKT_IN_PROGRESS' | 'TKT_CLOSED';
export type ResultCategory = 'TOTAL' | 'PARCIAL' | 'RECHAZO_TOTAL' | null;


export interface CerTicket {
  id: string;
  requirement_id: string;
  ticket_number: string;
  request_date: string;
  file_path: string;
  status: TicketStatus;
  result_category: ResultCategory;
  result_file: string | null;
}

export interface TicketGroup {
  ticketId: string;
  ticketNumber: string;
  rolesCount: number;
  roles: CerRole[];
}

// Agrega esta interfaz en tu archivo de modelos
export interface RejectionHistoryItem {
  ticket_number: string;
  reason: string;
  rejected_by: string; 
  rejected_at: string; 
}

// Actualiza tu interfaz CerRole existente
export interface CerRole {
  id: string;
  requirement_id: string;
  ticket_id?: string | null;
  status: string;
  rejection_reason?: string | null;
  rejection_history?: RejectionHistoryItem[] | null; 
  requirement_role?: {
    id: string;
    role_name: string;
  };
  ticket?: {
    id: string;
    ticket_number: string;
  };
}