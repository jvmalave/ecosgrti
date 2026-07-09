import { Component, OnInit, inject, signal, DestroyRef, computed } from '@angular/core';
import { CommonModule, UpperCasePipe } from '@angular/common';
import { Router } from '@angular/router';
import { Subscription } from 'rxjs';
import { ReactiveFormsModule, FormControl } from '@angular/forms'; // <-- 1. Importamos Formularios Reactivos
import { debounceTime, distinctUntilChanged } from 'rxjs/operators'; // <-- 2. Importamos Operadores RxJS
import { takeUntilDestroyed } from '@angular/core/rxjs-interop'; // <-- 3. Para prevenir Memory Leaks

// Imports de tus servicios e interfaces
import { AuthService } from '@ecosgrti/security/data-access';
import { RequirementService } from '../../data-access/services/requirement.service'; 
import { RequirementDashboard } from '../../data-access/models/requirement.model'; 
import { RequirementModalComponent } from '../requirement-modal/requirement-modal.component';
import { ApiResponse } from '../../data-access/models/api-response.model';
import { WorkflowStateService, AtfAgreementsModalComponent, AtfAgreementsListModalComponent, AtfAgreementDetail } from '@ecosgrti/workflow';


@Component({
  selector: 'lib-dashboard',
  standalone: true,
  // 4. Inyectamos ReactiveFormsModule aquí para poder usar [formControl] en el HTML
  imports: [CommonModule, UpperCasePipe, ReactiveFormsModule, RequirementModalComponent, AtfAgreementsModalComponent, AtfAgreementsListModalComponent], 
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss'
})
export class DashboardComponent implements OnInit {
  
  // ==========================================
  // 1. INYECCIÓN DE DEPENDENCIAS
  // ==========================================
  private refreshSub!: Subscription;
  public authService = inject(AuthService);
  private router = inject(Router);
  private requirementService = inject(RequirementService);
  private destroyRef = inject(DestroyRef); // Inyectado para gestionar la limpieza de suscripciones
  public readonly workflowState = inject(WorkflowStateService);

  // ==========================================
  // 2. ESTADO REACTIVO (SIGNALS Y FORM CONTROLS)
  // ==========================================

  
  public atfModalMode = signal<'create' | 'view'>('create');
  public selectedAgreementData = signal<AtfAgreementDetail | null>(null);
  public isSelectedReqAtfOpen = signal<boolean>(false);

  // Signals para el control del Modal ATF
  public isAtfModalOpen = signal<boolean>(false);
  public selectedReqForAtf = signal<string | null>(null);

  //  signals para el control del Modal de Detalle del Requerimiento
  isDetailModalOpen = signal<boolean>(false);
  selectedRequirementId = signal<string>('');

  // Signals para el control del Modal ATF List
  public isAtfListModalOpen = signal<boolean>(false);
  
  public user = this.authService.currentUser;

  context = signal<'PROCESO' | 'HISTORICO'>('PROCESO');
  requirements = signal<RequirementDashboard[]>([]);
  isLoading = signal<boolean>(true);
  
  currentOffset = signal<number>(0);
  hasMore = signal<boolean>(false);

  // Control Reactivo para el Buscador
  searchControl = new FormControl('');

  public selectedReqManagementType = computed(() => {
    const reqId = this.selectedReqForAtf();
    const allReqs = this.requirements();
    
    if (!reqId || !allReqs.length) {
        return 'Cargando...'; 
    }
    
    const foundReq = allReqs.find(req => req.id === reqId);
    
    if (foundReq) {
        return foundReq.management_type || foundReq.tipo_gestion || 'No Definido';
    }

    return 'No Definido';
  });

  // ==========================================
  // 3. CICLO DE VIDA
  // ==========================================
  ngOnInit(): void {
    // Restauración de Contexto (RN-07)
    const savedContext = localStorage.getItem('dashboard_context') as 'PROCESO' | 'HISTORICO';
    if (savedContext === 'PROCESO' || savedContext === 'HISTORICO') {
      this.context.set(savedContext);
    }

    // Suscripción Reactiva al Buscador
    this.searchControl.valueChanges.pipe(
      debounceTime(500),         // Espera 500ms sin teclear
      distinctUntilChanged(),    // Solo avanza si el texto realmente cambió
      takeUntilDestroyed(this.destroyRef) // Destruye la suscripción si el usuario sale del Dashboard
    ).subscribe(() => {
      this.currentOffset.set(0); // Si el usuario busca algo, debemos reiniciar la paginación a la página 1
      this.loadRequirements();
    });

    //Se escucha el "refresh" que viene del Modal
    this.requirementService.refreshDashboard$.pipe(
      takeUntilDestroyed(this.destroyRef)
    ).subscribe(() => {
      // Cuando el modal guarde, esta línea se ejecutará en silencio
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

  /**
   * Evalúa si abre el formulario directo o la lista de gestión.
   */
  public gestionarAcuerdos(req: RequirementDashboard): void {
    // 1. Guardamos el contexto
    this.selectedReqForAtf.set(req.id);
    this.isSelectedReqAtfOpen.set(req.status === 'ATF_OPEN');
    
    // 2. Reseteamos el formulario por si acaso
    this.atfModalMode.set('create');
    this.selectedAgreementData.set(null);

    // 3. SIEMPRE abrimos la lista primero (¡Adiós if/else!)
    this.isAtfListModalOpen.set(true);
  }

  public closeAtfModal(): void {
    this.isAtfModalOpen.set(false);
    this.selectedAgreementData.set(null);
    this.atfModalMode.set('create'); 
    
    // Como siempre entramos desde la lista, siempre regresamos a la lista
    this.isAtfListModalOpen.set(true);
  }

  public openAgreementCreate(): void {
    this.atfModalMode.set('create');
    this.selectedAgreementData.set(null);
    this.isAtfListModalOpen.set(false);
    this.isAtfModalOpen.set(true);
  }

  public openAgreementView(agreement: AtfAgreementDetail): void {
    this.atfModalMode.set('view');
    this.selectedAgreementData.set(agreement);
    this.isAtfListModalOpen.set(false); // Cerramos la lista
    this.isAtfModalOpen.set(true); // Abrimos el detalle
  }

  /**
   * Cierra el modal que contiene la lista de acuerdos ATF
   */
  public closeAtfListModal(): void {
    this.isAtfListModalOpen.set(false);
    this.selectedReqForAtf.set(null); // Limpiamos la selección
  }
  

  /**
   * Se ejecuta cuando el formulario emite que un acuerdo se guardó o actualizó exitosamente
   */
  public onAgreementSaved(): void {
    this.isAtfModalOpen.set(false);
    this.selectedAgreementData.set(null);
    this.isAtfListModalOpen.set(true);
  }

  /**
   * Abre el modal asignando el ID del requerimiento seleccionado.
   */
  openDetailModal(id: string): void {
    this.selectedRequirementId.set(id);
    this.isDetailModalOpen.set(true);
  }

  /**
   * Cierra el modal y refresca el dashboard si hubo cambios o borrados.
   */
  closeDetailModal(refreshDashboard = false): void {
    this.isDetailModalOpen.set(false);
    if (refreshDashboard) {
      // Aquí llamas a tu método para recargar la tabla (ej. this.loadRequirements())
    }
  }

  logout() {
    this.authService.logout().subscribe({
      next: () => {
        localStorage.removeItem('dashboard_context'); 
        this.router.navigate(['/login']);
      }
    });
  }

  /**
   * Navega a la vista de estimación del requerimiento seleccionado.
   */
  goToEstimation(requirementId: string): void {
    this.router.navigate(['/requerimientos', requirementId, 'estimacion']); 
  }

  /**
   * Abre el modal de Acuerdos ATF para un requerimiento específico.
   */
  public openAtfModal(requirementId: string): void {
    this.selectedReqForAtf.set(requirementId);
    this.isAtfModalOpen.set(true);
  }

  /**
   * Actualiza el estado local del requerimiento sin necesidad de recargar la página
   */
  public onRequirementMutated(event: { tipo_gestion: string, progreso_global: number }): void {
    const reqId = this.selectedReqForAtf();
    if (!reqId) return;

    // 🚀 Mutamos el Signal maestro inyectando los nuevos datos del backend
    this.requirements.update(reqs => 
      reqs.map(req => 
        req.id === reqId 
          ? { 
              ...req, 
              management_type: event.tipo_gestion, // Traducimos a la propiedad en inglés
              progress_percentage: event.progreso_global 
            } 
          : req
      )
    );
  }
}