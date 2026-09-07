
import { Component, OnInit, inject, signal, DestroyRef, computed } from '@angular/core';
import { CommonModule, UpperCasePipe } from '@angular/common';
import { Router } from '@angular/router';
import { Subscription } from 'rxjs';
import { ReactiveFormsModule, FormControl } from '@angular/forms'; 
import { debounceTime, distinctUntilChanged } from 'rxjs/operators'; 
import { takeUntilDestroyed } from '@angular/core/rxjs-interop'; 
import { HttpErrorResponse } from '@angular/common/http';
import Swal from 'sweetalert2';

// Imports de tus servicios e interfaces
import { AuthService } from '@ecosgrti/security/data-access';
import { PasswordChangeModalService, ReportService } from '@ecosgrti/shared';

// eslint-disable-next-line @nx/enforce-module-boundaries
import {
        RequirementService, 
        RequirementDashboard, 
        RequirementModalComponent, 
        ApiResponse, 
        EstimationFormComponent,
        RequirementCreateComponent,
        ReqStatusPipe,
        RequirementClosureModalComponent,
      } from '@sgrti/core';
      
import { 
        WorkflowStateService, 
        AtfAgreementsModalComponent, 
        AtfAgreementsListModalComponent, 
        AtfAgreementDetail,
        LifecycleOrchestratorModalComponent,
        WorkflowPhaseService,
        NotificationService    
      } from '@ecosgrti/workflow';
import { UnifiedPersonModalComponent, UnifiedPersonListModalComponent } from '@ecosgrti/security';
import { OrgStructureComponent, ProgressMatrixConfigComponent, MilestoneConfigComponent  } from '@ecosgrti/catalogs';


@Component({
  selector: 'app-dashboard',
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
    ReqStatusPipe,
    RequirementClosureModalComponent
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
  readonly phaseService = inject(WorkflowPhaseService);
  public readonly passwordChangeModalService = inject(PasswordChangeModalService);
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  // ==========================================
  // 2. ESTADO REACTIVO (SIGNALS Y FORM CONTROLS)
  // ==========================================

  // Signals para el control del Dropdown
  public isDropdownOpen = signal<boolean>(false);
  

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
  // SIGNALS PARA MÓDULO REPORTES
  // ==========================================
  public isReportsMenuOpen = signal<boolean>(false);

  /**
   * Alterna la visibilidad del submenú de reportes en el aside.
   */
  public toggleReportsMenu(): void {
    this.isReportsMenuOpen.update(open => !open);
  }




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

// Método para abrir/cerrar
public toggleDropdown(): void {
  this.isDropdownOpen.update((val) => !val);
}

public async cambiarContrasenaVoluntario(): Promise<void> {
  this.isDropdownOpen.set(false); // Cerramos el menú al hacer clic
  
  const data = await this.passwordChangeModalService.abrirModalCambioPassword();

  if (data) {
    Swal.fire({
      title: 'Actualizando credenciales...',
      allowOutsideClick: false,
      didOpen: () => { Swal.showLoading(); }
    });

    this.authService.changePassword(data).subscribe({
      next: (res) => {
        Swal.fire({
          title: '¡Bóveda Asegurada!',
          text: res.message,
          icon: 'success',
          customClass: { popup: 'rounded-4' },
          confirmButtonColor: '#0d6efd'
        });
      },
      error: (err: HttpErrorResponse) => {
        const errorMessage = err.error?.message || 'No se pudo actualizar la contraseña. Verifica tus datos.';
        Swal.fire({
          title: 'Error de Seguridad',
          text: errorMessage,
          icon: 'error',
          confirmButtonColor: '#0d6efd',
          customClass: { popup: 'rounded-4' }
        });
      }
    });
  }
}


public logoutDropdown(): void {
  this.isDropdownOpen.set(false); // Cerramos el menú al hacer clic
  this.authService.logout().subscribe({
      next: () => {
        localStorage.removeItem('dashboard_context'); 
        this.router.navigate(['/login']);
      }
    });
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
    const allowedStatuses = ['ES-R', 'ATF-I', 'ATF-C', 'DT-I', 'DT-C', 'COR-I', 'COR-C', 'COE-I', 'COE-C', 'CEE-I', 'CEE-C', 'CER-I', 'CER-C', 'PI-I', 'PI-C', 'PAP-I', 'PAP-C', 'AU-I', 'AU-C', 'RF'];
    return allowedStatuses.includes(status);
}

/**
 * Evalúa si el botón de "Gestión de Requerimiento (GR)" debe estar habilitado.
 * Se habilita ESTRICTAMENTE a partir del estatus 'ATF-C'.
 */
public isGrEnabled(status: string): boolean {
    // Array con los estados válidos donde GR debe estar accesible
    const allowedStatuses = ['ATF-C', 'DT-I', 'DT-C', 'COR-I', 'COR-C', 'COE-I', 'COE-C', 'CEE-I', 'CEE-C', 'CER-I', 'CER-C', 'PI-I', 'PI-C', 'PAP-I', 'PAP-C', 'AU-I', 'AU-C', 'RF']; 
    // Nota: Deberás agregar aquí los estados futuros como 'PROCESO-DT', 'CERRADO-DT', etc.
    return allowedStatuses.includes(status);
}


/**
   * RN-FR-01 (US37): Hard Gate de Cierre Histórico
   * Evalúa mediante el arreglo de fases congeladas (frozen_phases) 
   * si las líneas paralelas han culminado exitosamente.
   */
  public isCloseEnabled(req: RequirementDashboard): boolean {
      // Si ya está en histórico (RF), visor habilitado
      if (req.status === 'RF') return true;

      const tipologia = (req.management_type || req.tipo_gestion || '').toUpperCase();
      const frozen = req.frozen_phases || [];
      
      // Evalua la madurez absoluta basada en el sellado inmutable (frozen_phases)
      if (tipologia === 'ROLES') {
          return frozen.includes('AU');
      
      } else if (tipologia === 'ENTREGABLES') {
          return frozen.includes('CEE');
      
      } else if (tipologia === 'MIXTO') {
          // AMBAS líneas deben estar selladas
          return frozen.includes('AU') && frozen.includes('CEE');
      }

      return false; 
  }


  // ==========================================
  // SIGNALS Y MÉTODOS PARA EL MODAL DE CIERRE (US37)
  // ==========================================
  public selectedReqForClosure = signal<RequirementDashboard | null>(null);

  /**
   * Abre el modal de previsualización forense del Acta de Cierre.
   */
  public openClosureModal(req: RequirementDashboard): void {
      this.selectedReqForClosure.set(req);
  }

  /**
   * Cierra el modal abortando la operación (FS-01).
   */
  public closeClosureModal(): void {
      this.selectedReqForClosure.set(null);
  }

  /**
   * Transmutación Reactiva (Se dispara tras la confirmación atómica)
   */
  public onRequirementClosed(closedReqId: string): void {
      this.closeClosureModal();
      
      // Si esta en la pestaña "PROCESO", saca el requerimiento del arreglo de inmediato
      if (this.context() === 'PROCESO') {
          this.requirements.update(reqs => reqs.filter(r => r.id !== closedReqId));
      } else {
          // Si esta en "HISTORICO", solo recarga para actualizar el estatus.
          this.loadRequirements();
      }
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

    this.phaseService.refreshDashboard$.pipe(
      takeUntilDestroyed(this.destroyRef)
    ).subscribe(() => {
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
    
    // 7. Leemos el valor actual del buscador (si está nulo, mandamos undefined)
    const searchTerm = this.searchControl.value || undefined;

    // 8. Pasamos el searchTerm al servicio
    this.requirementService.getDashboardRequirements(
      apiStatus, 
      this.pageSize, 
      this.currentOffset(), 
      searchTerm
    ).subscribe({
      next: (response: ApiResponse<RequirementDashboard[]>) => {
        console.log('Requerimientos cargados', response);
        this.requirements.set(response.data);
        if (response.meta) {
          this.hasMore.set(response.meta.has_more);
        }

        // ====================================================================
        // REACTIVIDAD  PARA EL ORQUESTADOR
        // ====================================================================
        
        const currentSelected = this.selectedReqForOrchestrator(); 
        
        if (currentSelected) {
          // Busca el requerimiento actualizado en los datos recién llegados
          const freshReq = response.data.find(r => r.id === currentSelected.id);
          
          if (freshReq) {
            // Actualiza el Signal. Esto empuja los datos frescos al Orquestador
            // y los botones se iluminarán inmediatamente sin necesidad de recargar
            this.selectedReqForOrchestrator.set(freshReq);
          }
        }
        // ====================================================================

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

  public onRequirementPhaseUpdated(event: {req_id: string, phase_actual: string, progreso_global: number}): void {
    
    // 1. Mutamos la lista principal del Dashboard
    this.requirements.update(reqs => 
      reqs.map(req => 
        req.id === event.req_id 
          ? { 
              ...req, 
              status: event.phase_actual, 
              progress_percentage: event.progreso_global 
            } 
          : req
      )
    );

    // 2. Mutamos el requerimiento abierto en el Orquestador
    const currentOrchestratorReq = this.selectedReqForOrchestrator();
    if (currentOrchestratorReq && currentOrchestratorReq.id === event.req_id) {
      this.selectedReqForOrchestrator.set({
        ...currentOrchestratorReq,
        status: event.phase_actual,
        progress_percentage: event.progreso_global 
      });
    }

    console.log('✅ Requerimiento mutado reactivamente a fase:', event.phase_actual);
    this.loadRequirements();
  }

  public getRoleDisplayName(rawRole?: string): string { 
    if (!rawRole) return 'Sin Rol Asignado';

    const roleMap: Record<string, string> = {
      'admin': 'Administrador',
      'Admin': 'Administrador',
      'Coord': 'Coordinador',
      'ConsCSPE': 'Consultor CSPE',
      'Gerente': 'Gerente',
      'Viewer': 'Visualizador'
    };

    return roleMap[rawRole] || rawRole;
  } 

  /**
   * Verifica si el usuario activo tiene el rol de Administrador.
   */
  public get isAdmin(): boolean {
    const roles = this.user()?.roles || [];
    // Verificamos ambas variantes por si acaso quedó alguna en mayúscula en la BD
    return roles.includes('admin') || roles.includes('Admin');
  }

  public get isCoord(): boolean {
    const roles = this.user()?.roles || [];
    // Verificamos ambas variantes por si acaso quedó alguna en mayúscula en la BD
    return roles.includes('Coord') || roles.includes('coord');
  }

  // ==========================================
  // LÓGICA DE CONTROL DE ACCESO BASADO EN RECURSOS (ABAC)
  // ==========================================

  /**
   * Evalúa si el usuario activo tiene permisos de gestión sobre el requerimiento.
   * Se requiere ser Administrador, Coordinador, o ser un Consultor CSPE asignado explícitamente a este requerimiento.
   */
  public canManageRequirement(req: RequirementDashboard): boolean {
    const roles = this.user()?.roles || [];
    
    // Coordinadores y Administradores tienen control total
    if (roles.includes('Coord') || roles.includes('Admin') || roles.includes('admin')) {
      return true;
    }

    const myUserId = this.user()?.id;
    
    // Copia la data a una variable local para tratarla
    let consultores = req.cspe_consultants;

    // Si el backend (o Redis) envia un String en lugar de un Array, se parsea
    if (typeof consultores === 'string') {
      try {
        consultores = JSON.parse(consultores);
      } catch (e) {
        consultores = []; // Si falla el parseo, asumimos arreglo vacío
        console.log(e)
      }
    }
    
    // 🟢 Validación estricta: Solo usamos .some() si estamos 100% seguros de que es un Array
    if (Array.isArray(consultores) && consultores.length > 0) {
      const consultoresArray = consultores as { person_id: string }[];
      return consultoresArray.some(consultant => consultant.person_id === myUserId);
    }

    return false;
  }

  // Metodo para obtener las iniciales de un nombre completo
  getInitials(fullName: string | undefined | null): string {
  if (!fullName) return '';
  
  return fullName
    .trim()
    .split(' ')
    .filter(name => name.length > 0) // Evita errores si hay múltiples espacios
    .map(name => name.charAt(0))
    .join('')
    .toUpperCase();
  }

  /**
   * Gatilla la descarga del reporte de prueba bloqueando la UI con SweetAlert
   */
  descargarReportePrueba(): void {
    Swal.fire({
      title: 'Generando Reporte',
      text: 'Por favor espere mientras se compila el documento...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    this.reportService.downloadTestReport().subscribe({
      next: (blob: Blob) => {
        this.reportService.forceFileDownload(blob, 'reporte_conexion_ecosgrti.pdf');
        Swal.close();
      },
      error: (error) => {
        console.error('Error al generar el reporte:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error de Generación',
          text: 'No se pudo compilar el reporte PDF. Consulte los registros del servidor.',
          confirmButtonColor: '#0056b3'
        });
      }
    });
  }
/**
   * Gatilla la descarga del Directorio MDM bloqueando la UI con SweetAlert
   */
  descargarDirectorioMdm(): void {
    Swal.fire({
      title: 'Generando Directorio MDM',
      text: 'Compilando el registro de fichas unificadas, por favor espere...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    this.reportService.downloadMdmDirectoryReport().subscribe({
      next: (blob: Blob) => {
        // Retardo artificial de 1 segundo para UX antes de descargar y cerrar el modal
        setTimeout(() => {
          this.reportService.forceFileDownload(blob, 'directorio_fichas_mdm_ecosgrti.pdf');
          Swal.close();
        }, 1000);
      },
      error: (error) => {
        console.error('Error al generar el directorio MDM:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error de Generación',
          text: 'No se pudo compilar el directorio. Consulte los registros del servidor.',
          confirmButtonColor: '#0056b3'
        });
      }
    });
  }

// Gatilla el modal de reporte de seguimiento o acta de cierre para un requerimiento
  public async openReportModal(): Promise<void> {
    // 1. Solicitamos el RRTI usando tu servicio centralizado
    const rrti = await this.notificationService.promptTextInput(
      'Consultar Requerimiento',
      'Ingrese el código de control (RRTI) para generar su documento de seguimiento o acta de cierre:',
      'Ej: 00011114'
    );

    if (rrti) {
      // 2. Mostramos el Loading corporativo
      this.notificationService.showLoading(
        'Buscando en la bóveda...', 
        'Procesando la trazabilidad, por favor espere.'
      );

      const cleanRrti = rrti.replace('#', '').trim();

      // 3. Ejecutamos la petición HTTP
      this.reportService.downloadReportByRrti(cleanRrti).subscribe({
        next: (blob: Blob) => {
          this.reportService.forceFileDownload(blob, `Documento_RRTI_${cleanRrti}.pdf`);
          this.notificationService.close();
        },
        error: (err) => {
          console.error(err);
          // 4. Mostramos el error con tu alerta corporativa existente
          this.notificationService.showError(
            'Requerimiento No Encontrado',
            `No pudimos localizar un requerimiento con el código <strong>${cleanRrti}</strong>. Verifique e intente nuevamente.`
          );
        }
      });
    }
  }

 // Gatilla la descarga  del Reporte de Auditoría 

  public async triggerAuditLogReport(): Promise<void> {
    const filters = await this.notificationService.promptAuditLogFilters();

    if (filters) {
      this.notificationService.showLoading(
        'Procesando Trazas', 
        'Estructurando bitácora de auditoría. Este proceso puede tardar unos segundos.'
      );

      this.reportService.downloadAuditLog(filters).subscribe({
        next: (blob: Blob) => {
          const filename = `Bitacora_Auditoria_${filters.start_date}_al_${filters.end_date}.pdf`;
          this.reportService.forceFileDownload(blob, filename);
          this.notificationService.close(); // Cierra el loading exitosamente
        },
        error: (err) => {
          console.error('Error generando bitácora:', err);
          this.notificationService.showError(
            'Error de Extracción',
            'No se pudo generar la bitácora de auditoría. Verifique los parámetros o contacte a soporte.'
          );
        }
      });
    }
  }

}

