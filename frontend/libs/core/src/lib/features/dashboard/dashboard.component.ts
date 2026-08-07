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
import { RequirementDashboard, } from '../../data-access/models/requirement.model'; 
import { RequirementModalComponent } from '../requirement-modal/requirement-modal.component';
import { ApiResponse } from '../../data-access/models/api-response.model';
import { 
          WorkflowStateService, 
          AtfAgreementsModalComponent, 
          AtfAgreementsListModalComponent, 
          AtfAgreementDetail,
          LifecycleOrchestratorModalComponent    
        } from '@ecosgrti/workflow';
import { UnifiedPersonModalComponent, UnifiedPersonListModalComponent } from '@ecosgrti/security';
import { OrgStructureComponent, ProgressMatrixConfigComponent, MilestoneConfigComponent  } from '@ecosgrti/catalogs';
import { EstimationFormComponent } from '../estimation-form/estimation-form.component';
import { RequirementCreateComponent } from '../requirement-create/requirement-create.component';
import { ReqStatusPipe } from '../../pipes/req-status-pipe';



@Component({
  selector: 'lib-dashboard',
  standalone: true,
  // 4. Inyectamos ReactiveFormsModule aquí para poder usar [formControl] en el HTML
  imports: [
    CommonModule, 
    UpperCasePipe, 
    ReactiveFormsModule, 
    RequirementModalComponent, 
    AtfAgreementsModalComponent, 
    AtfAgreementsListModalComponent, 
    EstimationFormComponent, 
    RequirementCreateComponent,
    UnifiedPersonModalComponent,
    UnifiedPersonListModalComponent,
    OrgStructureComponent,
    ProgressMatrixConfigComponent,
    MilestoneConfigComponent,
    LifecycleOrchestratorModalComponent,
    ReqStatusPipe
  ], 
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

  public selectedReqCodeForAtf = signal<string>('');
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

  // Signals para el control del Modal de Orquestador
  selectedReqForOrchestrator = signal<RequirementDashboard | null>(null);
  
  public user = this.authService.currentUser;

  context = signal<'PROCESO' | 'HISTORICO'>('PROCESO');
  requirements = signal<RequirementDashboard[]>([]);
  isLoading = signal<boolean>(true);
  
  currentOffset = signal<number>(0);
  hasMore = signal<boolean>(false);
  public readonly pageSize = 10; 

  //Controlar el modal de estimación
  public isEstimationModalOpen = signal<boolean>(false);

  // Señal para controlar la visibilidad del modal de creación
  public isCreateModalOpen = signal<boolean>(false);

  // Señal para controlar la visibilidad del modal de estructura de organización
  public showOrgStructureModal = signal<boolean>(false);

  // Señal para controlar la visibilidad del modal de matriz de progreso
  public showProgressMatrixModal = signal<boolean>(false);

  // ==========================================
  // SIGNALS PARA MÓDULO DE CONFIGURACIÓN (MDM)
  // ==========================================
  public isConfigMenuOpen = signal<boolean>(false);
  public showUnifiedPersonListModal = signal<boolean>(false);
  // Verifica que tienes esta señal declarada
  public activeConfigModal = signal<'NONE' | 'UNIFIED_PERSON'>('NONE');

  public showMilestoneConfigModal = signal<boolean>(false);

  
  /**
   * Alterna la visibilidad del submenú de configuración en el aside.
   */
  public toggleConfigMenu(): void {
    this.isConfigMenuOpen.update(open => !open);
  }
  /**
   * Abre el modal del listado de fichas unificadas y cierra el submenú lateral.
   */
  public openUnifiedPersonListModal(): void {
    this.showUnifiedPersonListModal.set(true);
    this.isConfigMenuOpen.set(false);
  }
  /**
   * Cierra el modal del listado de fichas unificadas.
   */
  public closeUnifiedPersonListModal(): void {
    this.showUnifiedPersonListModal.set(false);
  }

  

// Abre el modal de configuración listando la ficha unificada
  public openConfigModal(modalType: 'NONE' | 'UNIFIED_PERSON'): void {
    this.activeConfigModal.set(modalType);
    this.isConfigMenuOpen.set(false); // Cierra el menú lateral tras elegir
  }
// Cierra el modal de configuración listando la ficha unificada
  public closeConfigModal(): void {
    //console.log('2. [Padre] Evento recibido en el Dashboard. Destruyendo el modal...');
    this.activeConfigModal.set('NONE');
  }
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
  //
  public selectedReqCreationDate = computed(() => {
    const reqId = this.selectedReqForAtf();
    const allReqs = this.requirements();
    
    if (!reqId || !allReqs.length) {
        return ''; 
    }
    
    const foundReq = allReqs.find(req => req.id === reqId);
    
    if (foundReq && foundReq.creation_date) {
        // Cortamos por espacio o por la 'T' para extraer únicamente la fecha (YYYY-MM-DD)
        // Esto previene errores si Laravel envía "2026-07-16 21:17:40" o "2026-07-16T21:17:40.000Z"
        return foundReq.creation_date.split(' ')[0].split('T')[0];
    }

    return '';
  });

  public isSelectedReqPlanningClosed = computed(() => {
    const reqId = this.selectedReqForAtf();
    const allReqs = this.requirements();
    const foundReq = allReqs.find(req => req.id === reqId);
    
    if (foundReq) {
        // Verifica que 'Req. Creado' o 'REQ_CREADO' coincida con tu base de datos
        return foundReq.status !== 'RC'; 
    }
    return true; 
  });

  /**
 * Evalúa si el botón de "Gestión de Acuerdos (ATF)" debe estar habilitado.
 * Se habilita a partir del estatus 'ES-R' (Planificación Cerrada).
 */
public isAtfEnabled(status: string): boolean {
    // Array con los estados válidos donde ATF debe estar accesible
    const allowedStatuses = ['ES-R', 'ATF-I', 'ATF-C', 'DT-I', 'DT-C'];
    return allowedStatuses.includes(status);
}

/**
 * Evalúa si el botón de "Gestión de Requerimiento (GR)" debe estar habilitado.
 * Se habilita ESTRICTAMENTE a partir del estatus 'ATF-C'.
 */
public isGrEnabled(status: string): boolean {
    // Array con los estados válidos donde GR debe estar accesible
    const allowedStatuses = ['ATF-C', 'DT-I', 'DT-C']; 
    // Nota: Deberás agregar aquí los estados futuros como 'PROCESO-DT', 'CERRADO-DT', etc.
    return allowedStatuses.includes(status);
}

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

  // MÉTODO PARA CARGAR REQUERIMIENTOS
  loadRequirements(): void {
    this.isLoading.set(true);
    const apiStatus = this.context() === 'PROCESO' ? 'active' : 'finalized';
    
    // 7. NUEVO: Leemos el valor actual del buscador (si está nulo, mandamos undefined)
    const searchTerm = this.searchControl.value || undefined;

    // 8. Pasamos el searchTerm al servicio
    this.requirementService.getDashboardRequirements(
      apiStatus, 
      this.pageSize, 
      this.currentOffset(), 
      searchTerm
    ).subscribe({
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

  // METODO PARA CAMBIAR EL CONTEXTO
  switchContext(newContext: 'PROCESO' | 'HISTORICO'): void {
    if (this.context() === newContext) return;

    this.context.set(newContext);
    this.currentOffset.set(0);
    
    // 9. Limpiamos el buscador al cambiar de pestaña sin disparar una doble petición
    this.searchControl.setValue('', { emitEvent: false }); 
    
    localStorage.setItem('dashboard_context', newContext);
    this.loadRequirements();
  }

  // METODO PARA GESTIONAR ACUERDOS
  public gestionarAcuerdos(req: RequirementDashboard): void {
    // 1. Guardamos el contexto
    this.selectedReqForAtf.set(req.id);
    this.selectedReqCodeForAtf.set(req.rrti);
    
    // 🚀 CORRECCIÓN: Arreglo de estados válidos de "Apertura"
    // Incluimos los estados previos o en progreso que permiten modificar acuerdos.
    // (Asegúrate de agregar aquí el texto exacto que envía tu Backend)
    const openStatuses = [
      'ATF-I',
      'ES-R', // Planificación Cerrada, listo para iniciar ATF
    ];
    
    this.isSelectedReqAtfOpen.set(openStatuses.includes(req.status));
    
    // 2. Reseteamos el formulario por si acaso
    this.atfModalMode.set('create');
    this.selectedAgreementData.set(null);

    // 3. SIEMPRE abrimos la lista primero (¡Adiós if/else!)
    this.isAtfListModalOpen.set(true);
  }

  // METODO QUE CIERRA EL MODAL DE  CREACION DE ACUERDO
  public closeAtfModal(): void {
    this.isAtfModalOpen.set(false);
    this.selectedAgreementData.set(null);
    this.atfModalMode.set('create'); 
    
    // Como siempre entramos desde la lista, siempre regresamos a la lista
    this.isAtfListModalOpen.set(true);
  }

  // METODO QUE ABRIR EL MODAL DE CREACIÓN DE ACUERDO
  public openAgreementCreate(): void {
    this.atfModalMode.set('create');
    this.selectedAgreementData.set(null);
    this.isAtfListModalOpen.set(false);
    this.isAtfModalOpen.set(true);
  }

  // METODO QUE ABRIR EL MODAL DEL LISTA DE ACUERDO
  public openAgreementView(agreement: AtfAgreementDetail): void {
    this.atfModalMode.set('view');
    this.selectedAgreementData.set(agreement);
    this.isAtfListModalOpen.set(false); // Cerramos la lista
    this.isAtfModalOpen.set(true); // Abrimos el detalle
  }

  // METODO QUE CIERRA EL MODAL DE LISTA DE ACUERDOS
  public closeAtfListModal(): void {
    this.isAtfListModalOpen.set(false);
    this.selectedReqForAtf.set(null); // Limpiamos la selección
  }
  
  // METODO QUE EMITE CUANDO SE GUARDA O ACTUALIZA UN ACUERDO
  public onAgreementSaved(): void {
    this.isAtfModalOpen.set(false);
    this.selectedAgreementData.set(null);
    this.isAtfListModalOpen.set(true);
  }

  // METODO PARA ABRIR EL MODAL DE DETALLE DE REQUERIMIENTO
  openDetailModal(id: string): void {
    this.selectedRequirementId.set(id);
    this.isDetailModalOpen.set(true);
  }

  // METODO PARA CERRAR EL MODAL DE DETALLE DE REQUERIMIENTO
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

  // METODO PARA ABRIR EL MODAL DE LISTA DE ACUERDOS
  public openAtfModal(req: RequirementDashboard): void {
    console.log('Abriendo modal para el requerimiento:', req);
    this.selectedReqForAtf.set(req.id);
    this.selectedReqCodeForAtf.set(req.rrti); 
    this.isAtfListModalOpen.set(true); 
  }

  // METODO PARA MANEJAR LA ACTUALIZACIÓN DE UN REQUERIMIENTO
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

  // MÉTODO PARA AVANZAR A LA PÁGINA SIGUIENTE
  public nextPage(): void {
    // Solo avanzamos si el backend nos confirmó que hay más registros
    if (this.hasMore()) {
      this.currentOffset.update(offset => offset + this.pageSize);
      this.loadRequirements();
    }
  }

  // MÉTODO PARA RETROCEDER A LA PÁGINA ANTERIOR
  public previousPage(): void {
    // Solo retrocedemos si no estamos en la primera página (offset > 0)
    if (this.currentOffset() > 0) {
      // Usamos Math.max para garantizar que el offset jamás sea negativo
      this.currentOffset.update(offset => Math.max(0, offset - this.pageSize));
      this.loadRequirements();
    }
  }

  // MÉTODO PARA ABRIR EL MODAL DE ESTIMACIÓN
  public openEstimationModal(req: RequirementDashboard): void {
    
    // Almacenamos los datos necesarios en las señales existente
    this.selectedReqForAtf.set(req.id);
    this.selectedReqCodeForAtf.set(req.rrti); 
    
    // Abrimos el nuevo modal
    this.isEstimationModalOpen.set(true);
  }

  // MÉTODO PARA CERRAR EL MODAL DE ESTIMACIÓN
  public closeEstimationModal(): void {
    this.isEstimationModalOpen.set(false);
  }

  // MÉTODO PARA ABRIR EL MODAL DE CREACIÓN DE REQUERIMIENTO
  public openCreateModal(): void {
    this.isCreateModalOpen.set(true);
  }

  // MÉTODO PARA CERRAR EL MODAL DE CREACIÓN DE REQUERIMIENTO
  public closeCreateModal(): void {
    this.isCreateModalOpen.set(false);
  }

  // MÉTODO PARA ABRIR EL MODAL DE ESTRUCTURA ORGANIZACIONAL
  public openOrgStructureModal(): void {
    this.showOrgStructureModal.set(true);
    this.isConfigMenuOpen.set(false);
  }

  // MÉTODO PARA CERRAR EL MODAL DE ESTRUCTURA ORGANIZACIONAL
  public closeOrgStructureModal(): void {
    this.showOrgStructureModal.set(false);
  }

  // MÉTODO PARA ABRIR EL MODAL DE CONFIGURACIÓN DE MATRIZ DE PROGRESO
  public openProgressMatrixModal(): void {
    this.showProgressMatrixModal.set(true);
    this.isConfigMenuOpen.set(false);
  }

  // MÉTODO PARA CERRAR EL MODAL DE CONFIGURACIÓN DE MATRIZ DE PROGRESO
  public closeProgressMatrixModal(): void {
    this.showProgressMatrixModal.set(false);
  }

  // MÉTODO PARA ABRIR EL MODAL DE CONFIGURACIÓN DE HITOS
  public openMilestoneConfigModal(): void {
    this.showMilestoneConfigModal.set(true);
    this.isConfigMenuOpen.set(false); // Cierra el menú lateral al abrir
  }
  
  // MÉTODO PARA CERRAR EL MODAL DE CONFIGURACIÓN DE HITOS
  public closeMilestoneConfigModal(): void {
    this.showMilestoneConfigModal.set(false);
  }

  // METODO PARA ABRIR EL MODAL DEL ORCHESTRATOR
  openLifecycleOrchestrator(req: RequirementDashboard): void {
    this.selectedReqForOrchestrator.set(req);
  }

  // MERTODO PARA CERRAR EL MODAL DEL ORCHESTRATOR
  closeLifecycleOrchestrator(): void {
    this.selectedReqForOrchestrator.set(null);
  }

}