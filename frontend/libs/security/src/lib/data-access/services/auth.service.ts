import { inject, Injectable, signal, PLATFORM_ID } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { UserSession } from '../models/auth.model';
import { AuthResponse } from '../models/auth.model';
import { isPlatformBrowser } from '@angular/common';
import { Observable, map, finalize } from 'rxjs';
import { Router } from '@angular/router';
import { AUTH_API_URL } from '../tokens/tokens';


@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private router = inject(Router);
  private readonly http = inject(HttpClient);
  private readonly apiUrl = inject(AUTH_API_URL);

  private readonly _currentUser = signal<UserSession | null>(
    inject(PLATFORM_ID) && isPlatformBrowser(inject(PLATFORM_ID))
      ? (() => {
          const saved = localStorage.getItem('ecosgrti_session');
          return saved ? JSON.parse(saved) : null;
        })()
      : null
  );
  
  public readonly currentUser = this._currentUser.asReadonly();
  private platformId = inject(PLATFORM_ID);
  public readonly currentSession = this._currentUser.asReadonly();

  constructor() {
    // Al iniciar el servicio, se carga el token guardado en el Signal con el localStorage.
  }

  public login(credentials: { username: string; password: string }): Observable<UserSession> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, credentials).pipe(
      map((response: AuthResponse) => { 
        
        // Construye el objeto con la estructura que el Frontend espera
        const mappedSession: UserSession = {
          id: response.user.id || '1',
          username: response.user.username || response.user.name, 
          fullName: response.user.fullName || response.user.username || 'Usuario', 
          email: response.user.email,
          roles: response.user.roles || [],
          token: response.access_token 
        };

        // Guarda los datos mapeados en el Signal de memoria
        this._currentUser.set(mappedSession);
        
        // Guarda en el almacenamiento físico del navegador
        if (isPlatformBrowser(this.platformId)) {
          localStorage.setItem('ecosgrti_session', JSON.stringify(mappedSession));
        }

        // Retorna el objeto transformado
        return mappedSession;
      })
    );
  }

  /**
   * Envía la solicitud de cambio de contraseña al backend.
   * El interceptor HTTP de Angular se encarga de inyectar el token Bearer.
   */
  public changePassword(data: { current_password: string; new_password: string }): Observable<{ success: boolean; message: string }> {
    return this.http.post<{ success: boolean; message: string }>(
      `${this.apiUrl}/change-password`, 
      data
    );
  }

  public logout(): Observable<void> {
    // Devolver la petición para que el componente (o el test) pueda suscribirse
    return this.http.post<void>(`${this.apiUrl}/logout`, {}).pipe(
      // finalize ejecuta pase lo que pase (éxito o error), reemplazando tu next/error
      finalize(() => {
        this.clearLocalSession();
      })
    );
  }

  /**
   * Método auxiliar privado para limpiar el rastro local.
   */
  private clearLocalSession(): void {
    // Vacia el Signal (la UI reacciona instantáneamente)
    this._currentUser.set(null);

    console.log('Platform ID es:', this.platformId);
    
    // Borra el almacenamiento físico
    if (isPlatformBrowser(this.platformId)) {
      localStorage.removeItem('ecosgrti_session');
    }

    // Redirige a la vista de login
    this.router.navigate(['/login']);
  }
}