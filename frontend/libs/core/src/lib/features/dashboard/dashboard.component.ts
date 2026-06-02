import { Component, OnInit, inject, signal, DestroyRef } from '@angular/core';
import { CommonModule, UpperCasePipe } from '@angular/common';
import { Router } from '@angular/router';
import { ReactiveFormsModule, FormControl } from '@angular/forms'; // <-- 1. Importamos Formularios Reactivos
import { debounceTime, distinctUntilChanged } from 'rxjs/operators'; // <-- 2. Importamos Operadores RxJS
import { takeUntilDestroyed } from '@angular/core/rxjs-interop'; // <-- 3. Para prevenir Memory Leaks

// Imports de tus servicios e interfaces
import { AuthService } from '@ecosgrti/security/data-access';
import { RequirementService } from '../../data-access/services/requirement.service'; 
import { RequirementDashboard } from '../../data-access/models/requirement.model'; 
import { ApiResponse } from '../../data-access/models/api-response.model';

@Component({
  selector: 'lib-dashboard',
  standalone: true,
  // 4. Inyectamos ReactiveFormsModule aquí para poder usar [formControl] en el HTML
  imports: [CommonModule, UpperCasePipe, ReactiveFormsModule], 
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss'
})
export class DashboardComponent implements OnInit {
  
  // ==========================================
  // 1. INYECCIÓN DE DEPENDENCIAS
  // ==========================================
  public authService = inject(AuthService);
  private router = inject(Router);
  private requirementService = inject(RequirementService);
  private destroyRef = inject(DestroyRef); // Inyectado para gestionar la limpieza de suscripciones

  // ==========================================
  // 2. ESTADO REACTIVO (SIGNALS Y FORM CONTROLS)
  // ==========================================
  
  public user = this.authService.currentUser;

  context = signal<'PROCESO' | 'HISTORICO'>('PROCESO');
  requirements = signal<RequirementDashboard[]>([]);
  isLoading = signal<boolean>(true);
  
  currentOffset = signal<number>(0);
  hasMore = signal<boolean>(false);

  // 5. NUEVO: Control Reactivo para el Buscador
  searchControl = new FormControl('');

  // ==========================================
  // 3. CICLO DE VIDA
  // ==========================================
  ngOnInit(): void {
    // Restauración de Contexto (RN-07)
    const savedContext = localStorage.getItem('dashboard_context') as 'PROCESO' | 'HISTORICO';
    if (savedContext === 'PROCESO' || savedContext === 'HISTORICO') {
      this.context.set(savedContext);
    }

    // 6. NUEVO: Suscripción Reactiva al Buscador
    this.searchControl.valueChanges.pipe(
      debounceTime(500),         // Espera 500ms sin teclear
      distinctUntilChanged(),    // Solo avanza si el texto realmente cambió
      takeUntilDestroyed(this.destroyRef) // Destruye la suscripción si el usuario sale del Dashboard
    ).subscribe(() => {
      this.currentOffset.set(0); // Si el usuario busca algo, debemos reiniciar la paginación a la página 1
      this.loadRequirements();
    });

    // Carga Inicial
    this.loadRequirements();
  }

  // ==========================================
  // 4. LÓGICA DE NEGOCIO (MÉTODOS)
  // ==========================================

  loadRequirements(): void {
    this.isLoading.set(true);
    const apiStatus = this.context() === 'PROCESO' ? 'active' : 'finalized';
    
    // 7. NUEVO: Leemos el valor actual del buscador (si está nulo, mandamos undefined)
    const searchTerm = this.searchControl.value || undefined;

    // 8. Pasamos el searchTerm al servicio
    this.requirementService.getDashboardRequirements(apiStatus, 10, this.currentOffset(), searchTerm).subscribe({
      next: (response: ApiResponse<RequirementDashboard[]>) => {
        this.requirements.set(response.data);
        if (response.meta) {
          this.hasMore.set(response.meta.has_more);
        }
        this.isLoading.set(false);
      },
      error: (err) => {
        console.error('Error cargando el dashboard', err);
        this.isLoading.set(false);
      }
    });
  }

  switchContext(newContext: 'PROCESO' | 'HISTORICO'): void {
    if (this.context() === newContext) return;

    this.context.set(newContext);
    this.currentOffset.set(0);
    
    // 9. Limpiamos el buscador al cambiar de pestaña sin disparar una doble petición
    this.searchControl.setValue('', { emitEvent: false }); 
    
    localStorage.setItem('dashboard_context', newContext);
    this.loadRequirements();
  }

  logout() {
    this.authService.logout().subscribe({
      next: () => {
        localStorage.removeItem('dashboard_context'); 
        this.router.navigate(['/login']);
      }
    });
  }
}