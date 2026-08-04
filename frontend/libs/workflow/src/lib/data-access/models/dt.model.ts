export interface DtRole {
  id: string;
  requirement_id: string;
  requirement_role_id: string;
  name: string;
  status: 'IN_PROGRESS' | 'CLOSED';
}

export interface DtRegister {
  id: string;
  role_id: string;
  title: string;
  date: string;
  description: string;
  created_at?: string;
  updated_at?: string;
}

export interface DtRoleInitResponse {
  requirement_id: string;
  roles_list: DtRole[];
}

export interface DtRoleStatusChangeResponse {
  role_id: string;
  new_status: 'IN_PROGRESS' | 'CLOSED';
}

export interface ActionMessageResponse {
  message: string;
}