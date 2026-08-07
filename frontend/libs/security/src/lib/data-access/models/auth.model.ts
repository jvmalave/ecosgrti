export interface UserSession {
  id: string;
  username: string;
  email: string;
  roles: string[];
  token: string; 
}

export interface AuthResponse {
  access_token: string; 
  token_type: string;
  expires_in: number;
  user: {
    name: string;
    email: string;
    id?: string;
    roles?: string[];
  };
}