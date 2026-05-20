export interface UserSession {
  id: string;
  username: string;
  email: string;
  roles: string[];
  token: string; // El guard seguirá buscando esta propiedad 🔑
}

export interface AuthResponse {
  access_token: string; // Coincide exactamente con el JSON de Laravel 🐳
  token_type: string;
  expires_in: number;
  user: {
    name: string;
    email: string;
    id?: string;
    roles?: string[];
  };
}