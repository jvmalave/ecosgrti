// src/app/domains/security/models/unified-person.models.ts

// ==========================================
// Interfaces Base (Proporcionadas)
// ==========================================
export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

export interface RequestingUnitOption {
  id: string;
  name: string;
}

export interface UnifiedPersonResponse {
  success: boolean;
  message: string;
  data: UnifiedPerson; // Ajustado para coincidir con la respuesta de Laravel Resource
}

export interface UnifiedPersonPayload {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string | null;
  is_functional: boolean;
  is_cspe: boolean;
  has_system_access: boolean;
  requesting_unit_id?: string | null;
  name?: string;
  password?: string;
  roles?: string[];
}

// ==========================================
// Interfaces de Lectura (Mapeo del API Resource)
// ==========================================
export interface UserProfile {
  username: string;
  roles: string[];
}

export interface FunctionalProfile {
  requesting_unit_id: string;
}

export interface CspeProfile {
  id: string;
}

export interface UnifiedPersonProfiles {
  user?: UserProfile;
  functional?: FunctionalProfile;
  cspe?: CspeProfile;
}

export interface UnifiedPerson {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  is_functional: boolean;
  is_cspe: boolean;
  has_system_access: boolean;
  profiles: UnifiedPersonProfiles;
  created_at: string;
}

// ==========================================
// Paginación (Laravel AnonymousResourceCollection)
// ==========================================
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}