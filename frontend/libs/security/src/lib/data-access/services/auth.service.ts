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
  //private readonly apiUrl = 'http://localhost:8000/api/auth'; 

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
    // Si estamos en el navegador, sincronizamos el Signal con el localStorage al cargar el servicio
  }

  public login(credentials: { email: string; password: string }): Observable<UserSession> {
      return this.http.post<AuthResponse>(`${this.apiUrl}/login`, credentials).pipe(
        map((response: AuthResponse) => { // Usamos map para transformar el flujo
          
          // 1. Se Construye el objeto con la estructura que el Frontend espera
          const mappedSession: UserSession = {
            id: response.user.id || '1',
            username: response.user.name, 
            email: response.user.email,
            roles: response.user.roles || ['ADMIN'],
            token: response.access_token //  Asignamos 'access_token' a 'token'
          };

          // 2. Se Guarda los datos mapeados en el Signal de memoria
          this._currentUser.set(mappedSession);
          
          // 3. Se Guarda en el almacenamiento físico del navegador
          if (isPlatformBrowser(this.platformId)) {
            localStorage.setItem('ecosgrti_session', JSON.stringify(mappedSession));
          }

          // 4. Retorna el objeto transformado para que coincida con Observable<UserSession>
          return mappedSession;
        })
      );
    }

  /**
   * Logout completo: Invalida en servidor y limpia cliente
   */
  // public logout(): void {
  //   // 1. Se llama a Laravel para destruir el token en Redis
  //   this.http.post(`${this.apiUrl}/logout`, {}).subscribe({
  //     next: () => {
  //       this.clearLocalSession();
  //     },
  //     error: (err) => {
  //       console.warn('El servidor devolvió un error al cerrar sesión, forzando cierre local.', err);
  //       this.clearLocalSession();
  //     }
  //   });
  // }

  public logout(): Observable<void> {
  // 1. Devolvemos la petición para que el componente (o el test) pueda suscribirse
  return this.http.post<void>(`${this.apiUrl}/logout`, {}).pipe(
    // 2. finalize se ejecuta pase lo que pase (éxito o error), reemplazando tu next/error
    finalize(() => {
      this.clearLocalSession();
    })
  );
}

  /**
   * Método auxiliar privado para limpiar el rastro local.
   */
  private clearLocalSession(): void {
    // Se vacia el Signal (la UI reacciona instantáneamente)
    this._currentUser.set(null);

    console.log('Platform ID es:', this.platformId);
    
    // Se borra el almacenamiento físico
    if (isPlatformBrowser(this.platformId)) {
      localStorage.removeItem('ecosgrti_session');
    }

    // Redirige a la vista de login
    this.router.navigate(['/login']);
  }
}