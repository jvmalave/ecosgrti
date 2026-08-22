export type CertStatus = 'PENDING_CERTIFICATION' | 'IN_PROGRESS' | 'CERTIFIED';
export type TicketStatus = 'TKT_IN_PROGRESS' | 'TKT_CLOSED';
export type ResultCategory = 'TOTAL' | 'PARCIAL' | 'RECHAZO_TOTAL' | null;

export interface CeeTicket {
  id: string;
  requirement_id: string;
  ticket_number: string;
  request_date: string;
  file_path: string;
  status: TicketStatus;
  result_category: ResultCategory;
  result_file: string | null;
}

export interface CeeRejectionHistoryItem {
  ticket_number: string;
  reason: string;
  rejected_by: string; 
  rejected_at: string; 
}

export interface CeeDeliverable {
  id: string;
  requirement_id: string;
  ticket_id?: string | null;
  status: string;
  rejection_reason?: string | null;
  rejection_history?: CeeRejectionHistoryItem[] | null; 
  
  // Relación con el entregable técnico de la fase COE
  deliverable?: {
    id: string;
    name: string;
  };
  
  ticket?: {
    id: string;
    ticket_number: string;
  };
}