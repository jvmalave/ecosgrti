export interface DtRole {
  id: string;
  requirement_id: string;
  requirement_role_id: string;
  name: string;
  status: 'IN_PROGRESS' | 'CLOSED';
  registers_count?: number;
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


export interface DtRegistersResponse {
  id_req: string;
  nombre_rol: string;
  estado_rol: string;
  registros: DtRegister[];
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