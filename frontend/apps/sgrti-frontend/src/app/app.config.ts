import {
  ApplicationConfig,
  provideBrowserGlobalErrorListeners,
  provideZoneChangeDetection,
} from '@angular/core';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { appRoutes } from './app.routes';
import { authInterceptor } from '@ecosgrti/security/data-access';
import { AUTH_API_URL } from '@ecosgrti/security/data-access';
import { environment } from '../environments/environment.development';
import { httpErrorInterceptor } from '../core/interceptors/http-error.interceptor';
import { NotificationService } from '@app/workflow';
import {
  provideClientHydration,
  withEventReplay,
} from '@angular/platform-browser';


export const appConfig: ApplicationConfig = {
  providers: [
    provideClientHydration(withEventReplay()),
    provideBrowserGlobalErrorListeners(),
    provideRouter(appRoutes, withComponentInputBinding()),
    provideZoneChangeDetection({ eventCoalescing: true }),
    NotificationService,
    provideHttpClient(
      withFetch(),
      withInterceptors([authInterceptor])
    ),
    provideHttpClient(withInterceptors([httpErrorInterceptor])),
    { provide: AUTH_API_URL, useValue: environment.authApiUrl },
    { provide: 'GLOBAL_API_URL', useValue: environment.apiUrl },
    
  ],
};
