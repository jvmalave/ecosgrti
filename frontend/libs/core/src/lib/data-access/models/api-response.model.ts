
/**
 * Interfaz genérica para estandarizar las respuestas del backend de Laravel.
 * Permite tipar de forma estricta la data (T) y la paginación (meta).
 */
export interface ApiResponse<T> {
  message?: string;
  data: T;
  meta?: {
    has_more: boolean;
    total_returned: number;
    offset: number;
    limit: number;
  };
}

