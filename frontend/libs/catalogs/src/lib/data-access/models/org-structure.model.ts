
export interface Society {
  id: string; 
  name: string;
  acronym?: string; 
  is_active: boolean; 
}

export interface SystemNode {
  id: string; 
  society_id: string; 
  name: string;
  is_active: boolean; 
}

export interface RequestingUnit {
  id: string; 
  system_id: string; 
  name: string;
  is_active: boolean; 
}

export interface OrgTreeResponse {
  data: {
    societies: Society[];
    systems: SystemNode[];
    requesting_units: RequestingUnit[];
  };
}