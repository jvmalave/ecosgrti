export type ManagementType = 'ROLES' | 'ENTREGABLES' | 'MIXTO';

export interface Milestone {
  id?: string;
  name: string;
  weight: number; 
  milestone_id?: string;
}

export interface ProgressMatrix {
  matrix_id: string;
  version_number: number;
  management_type: ManagementType;
  milestones: Milestone[];
}

export interface PublishMatrixPayload {
  management_type: ManagementType;
  milestones: Milestone[];
}

export interface PublishResponse {
  message: string;
  matrix_id: string;
}


