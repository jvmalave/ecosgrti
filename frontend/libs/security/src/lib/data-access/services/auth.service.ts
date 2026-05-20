import { inject, Injectable, signal, PLATFORM_ID } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { UserSession } from '../models/auth.model';
import { AuthResponse } from '../models/auth.model';
import { isPlatformBrowser } from '@angular/common';
import { Observable, map } from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api/auth'; 

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

  

  constructor() {
    // Si estamos en el navegador, sincronizamos el Signal con el localStorage al cargar el servicio
  }

public login(credentials: { email: string; password: string }): Observable<UserSession> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, credentials).pipe(
      map((response: AuthResponse) => { // Usamos map para transformar el flujo
        
        // 1. Construimos el objeto con la estructura que el Frontend espera
        const mappedSession: UserSession = {
          id: response.user.id || '1',
          username: response.user.name, 
          email: response.user.email,
          roles: response.user.roles || ['ADMIN'],
          token: response.access_token //  Asignamos 'access_token' a 'token'
        };

        // 2. Guardamos los datos mapeados en el Signal de memoria
        this._currentUser.set(mappedSession);
        
        // 3. Guardamos en el almacenamiento físico del navegador
        if (isPlatformBrowser(this.platformId)) {
          localStorage.setItem('ecosgrti_session', JSON.stringify(mappedSession));
        }

        // 4. Retornamos el objeto transformado para que coincida con Observable<UserSession>
        return mappedSession;
      })
    );
  }

  public logout(): void {
    this._currentUser.set(null);
    if (isPlatformBrowser(this.platformId)) {
      localStorage.removeItem('ecosgrti_session');
    }
  }
}