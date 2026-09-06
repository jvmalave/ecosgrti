export interface PapOrder {
  id: string;
  requirement_id: string;
  order_number: string;
  date: string;
  file_path: string;
  status: 'ORD_IN_PROGRESS' | 'ORD_CLOSED';
  result_category?: string | null;
  result_file?: string | null;
  created_at?: string;
}

export interface RejectionHistoryItem {
  order_number: string;
  reason: string;
  rejected_by: string; 
  rejected_at: string; 
}

export interface PapRole {
  id: string;
  requirement_id: string;
  requirement_role_id: string;
  order_id?: string | null;
  status: 'PENDING_PAP' | 'IN_PROGRESS' | 'IN_PRODUCTION';
  fail_reason?: string | null;
  rejection_history?: RejectionHistoryItem[] | null;
  requirement_role?: {
    id: string;
    role_name: string;
  }; 
  order?: PapOrder | null;
}

// Interfaz auxiliar para agrupar en la vista (Espejo de TicketGroup)
export interface OrderGroup {
  orderId: string;
  orderNumber: string;
  rolesCount: number;
  roles: PapRole[];
}

export interface PapRolesInitResponse {
  requirement_id: string;
  roles: PapRole[];
}

export interface PapOrderResponse {
  message: string;
  order_id?: string;
  phase_actual?: string;
  progress_percentage?: number;
}

export interface PapResultResponse {
  message: string;
}

export type SuccessfulOrder = {
    id: string;
    order_number: string;
    roles_count: number;
  };

  export interface ClosePhaseResponse {
  message?: string;
  progress_percentage?: number;
}