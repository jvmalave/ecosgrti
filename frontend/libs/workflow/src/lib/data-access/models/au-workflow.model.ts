// frontend/libs/workflow/src/lib/data-access/models/au-workflow.model.ts

export interface AuRole {
  id: string;
  requirement_id: string;
  requirement_role_id: string;
  ticket_id?: string | null;
  status: 'PENDING_AU' | 'IN_PROGRESS' | 'ASSIGNED';
  planilla_path?: string | null;
  rejection_reason?: string | null;
  role_name?: string; 
  requirement_role?: {
    role_name: string;
  };
  ticket?: {
    ticket_number: string;
    file_path?: string;
    result_file_path?: string;
    result_file?: string;
  };
  rejection_history?: Array<{
    ticket_number?: string;
    rejected_at: string;
    reason: string;
  }>;
}

export interface AuRolesInitResponse {
  requirement_id: string;
  roles: AuRole[];
}

export interface TicketGroup {
  ticketId: string;
  ticketNumber: string;
  rolesCount: number;
  roles: AuRole[];
}

export interface AuRoleWithRelation extends AuRole {
  ticket?: {
    ticket_number: string;
  };
}

export interface AuTicket {
  id: string;
  requirement_id: string;
  ticket_number: string;
  request_date: string;
  file_path: string;
  status: string;
  result_category?: string | null;
  result_file?: string | null;
}

export interface AuTicketResponse {
  success: boolean;
  message: string;
  data: AuTicket;
}

export interface EvaluationData {
  id: string;
  role_name: string;
  is_approved: boolean | string;
  rejection_reason: string;
}

export interface FormEvaluationData {
  id: string;
  role_name: string;
  verdict: 'TOTAL' | 'PARCIAL' | 'NO_ASIGNADO';
  rejection_reason: string;
}
