export interface Milestone {
  id?: string; 
  phase: string;
  phase_code: string;
  name: string;
  status_code: string;
  default_weight: number;
  management_type: 'ROLES' | 'ENTREGABLES' | 'MIXTO';
  created_at?: string;
  updated_at?: string;
}