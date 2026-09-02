import { Component, inject, input, output, signal, computed, OnInit, OnDestroy, ProviderToken } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { Subject, takeUntil } from 'rxjs';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ProgressDashboardComponent } from '../progress-dashboard/progress-dashboard.component';
import { 
  RequirementDetail, 
  OrganizationalGraph, 
  FunctionalConsultantItem, 
  RequirementCspeRelation,
  CspeConsultantItem,
  CatalogItem,
  DeletionTicketResponse
} from '../../data-access/models/requirement.model'; 
import { RequirementService } from '../../data-access/services/requirement.service';
import Swal from 'sweetalert2';




@Component({
  selector: 'lib-requirement-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ProgressDashboardComponent],
  templateUrl: './requirement-modal.component.html',
  styleUrls: ['./requirement-modal.component.scss']
})
export class RequirementModalComponent implements OnInit, OnDestroy {
  // --- ENTRADAS, SALIDAS Y SERVICIOS ---
  public currentRequirement = signal<RequirementDetail | null>(null);
  requirementId = input<string | null>(null);
  modalClosed = output<void>();
  

  private router = inject(Router);
  
  private apiUrl = inject('GLOBAL_API_URL' as unknown as ProviderToken<string>);
  private requirementService = inject(RequirementService);
  private fb = inject(FormBuilder);

  // --- ESTADOS Y SIGNALS ---
  isEditing = signal<boolean>(false);
  isLocked = signal<boolean>(false);
  isLoading = signal<boolean>(true);
  requirementTypes = signal<CatalogItem[]>([]);
  managementTypes = signal<CatalogItem[]>([]);

  // Variables para almacenar los archivos nuevos antes de enviarlos
  newFiles: { itReq: File | null, needsSpread: File | null } = { itReq: null, needsSpread: null };

  // Signals para almacenar datos específicos del requerimiento
  organizationalData = signal<OrganizationalGraph | null>(null);
  attachedDocs = signal<{itReq: string | null, needsSpread: string | null}>({ itReq: null, needsSpread: null });
  
  // Signals para almacenar los catálogos completos 
  functionalConsultants = signal<FunctionalConsultantItem[]>([]);
  cspeConsultants = signal<CspeConsultantItem[]>([]); 

  // Signals para guardar los IDs puros de la Base de Datos
  rawConsultantId = signal<string>(''); 
  currentCspeIds = signal<string[]>([]);

  // --- FORMULARIO REACTIVO ---
  editForm: FormGroup;
  private destroy$ = new Subject<void>();

  // --- COMPUTEDS ---
  selectedConsultantName = computed(() => {
    const rawId = this.rawConsultantId();
    const catalog = this.functionalConsultants();

    if (!rawId) return 'Ningún consultor asignado';
    if (catalog.length === 0) return 'Cargando consultores...';

    // Intenta buscar por ID de base de datos (Manzana), si no está, busca por persona_id (Naranja)
    const consultant = catalog.find(c => c.id === rawId || c.persona_id === rawId);
    
    return consultant ? consultant.full_name : '⚠️ Consultor no hallado en el catálogo';
  });

  constructor() {
    this.editForm = this.fb.group({
      rrti: [''],
      requirement_type: [''],
      management_type: [''],
      creation_date: [''],
      description: [''],
      persona_id: [''],
      cspe_consultants: [[]]
    });
  }

  ngOnInit(): void {
    this.loadConsultantsCatalogs();

    if (this.requirementId()) {
      this.loadRequirementDetails();
    }
    this.loadCatalogs();

    this.listenToConsultantChanges();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // Carga de catálogos consultores
  private loadConsultantsCatalogs(): void {
    this.requirementService.getFunctionalConsultants().pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        console.log('Data de Consultores desde Laravel:', res.data); 
        this.functionalConsultants.set(res.data);
        
      }
    });

    this.requirementService.getCspeConsultants().pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => this.cspeConsultants.set(res.data)
    });
  }

  // Carga de detalles del requerimiento
  private loadRequirementDetails(): void {
    this.isLoading.set(true);
    
    const id = this.requirementId();
    if (!id) return;

    this.requirementService.getRequirementDetail(id).pipe(takeUntil(this.destroy$)).subscribe({
      next: (response) => {
        // Usamos 'any' o ajustamos tu interfaz RequirementDetail
        const data: RequirementDetail = response.data;
        this.isLocked.set(data.is_locked);
        
        // 1. Organigrama Histórico
        const snapshotGraph: OrganizationalGraph = {
          // El organigrama usa el ID para propósitos internos
          persona_id: data.functional_consultant_id, 
          functional_consultant_id: data.functional_consultant_id,
          requesting_unit_name: data.snapshot_unit_name || 'No registrada',
          system_name: data.snapshot_system_name || 'No registrado',
          society_name: data.snapshot_society_name || 'No registrada'
        };
        this.organizationalData.set(snapshotGraph);

        // 2. Archivos Adjuntos
        this.attachedDocs.set({
          itReq: data.it_request_doc_path || null,
          needsSpread: data.needs_spreadsheet_path || null
        });

        // 3. Guardar IDs Puros en memoria
        this.rawConsultantId.set(data.functional_consultant_id);
        
        // Mapeo de IDs CSPE
        const mappedCspe = data.cspe_consultants 
          ? data.cspe_consultants.map((c: RequirementCspeRelation) => c.pivot ? c.pivot.cspe_consultant_id : c.id) 
          : [];
        
        this.currentCspeIds.set(mappedCspe);

        // 🟢 MAGIA: Extraemos el persona_id desde la relación anidada de Laravel
        //const realPersonaId = data.functional_consultant?.person_id || data.functional_consultant_id;
        const realPersonaId = (data as RequirementDetail).functional_consultant?.person_id;

        // 4. Hidratar Formulario y Bloquear
        this.editForm.patchValue({
          rrti: data.rrti,
          requirement_type: data.requirement_type,
          management_type: data.management_type,
          creation_date: data.creation_date ? data.creation_date.substring(0, 10) : '',
          description: data.description,
          persona_id: realPersonaId, 
          cspe_consultants: mappedCspe
        });
        
        // =========================================================
        // ALIMENTAR ESTADO REACTIVO (Para la barra de progreso y banner)
        // =========================================================
        this.currentRequirement.set(data as RequirementDetail);

        // Como indicaste: Siempre bloqueamos al inicio (Modo Solo Lectura)
        this.editForm.disable(); 
        
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
      }
    });
  }

  
  // Cargar catálogos dinámicos tipos de requerimiento y de gestión
  loadCatalogs(): void {
    // 1. Cargar Tipos de Requerimiento
    this.requirementService.getRequirementTypes().subscribe({
      next: (response) => {
        if (response && response.data) {
          this.requirementTypes.set(response.data);
        }
      },
      error: (err) => console.error('Error al cargar tipos de requerimiento:', err)
    });

    // 2. Cargar Tipos de Gestión
    this.requirementService.getManagementTypes().subscribe({
      next: (response) => {
        if (response && response.data) {
          this.managementTypes.set(response.data);
        }
      },
      error: (err) => console.error('Error al cargar tipos de gestión:', err)
    });
  }

  // Verificación de IDs
  isCspeSelected(cspeId: string): boolean {
    const currentValues = this.isEditing() ? (this.editForm.getRawValue().cspe_consultants || []) : this.currentCspeIds();
    return currentValues.map(String).includes(String(cspeId));
  }

  // Cierre del Modal
  closeModal(): void {
    this.modalClosed.emit();
  }

  // Obtención de URL de Documento
  getDocumentUrl(path: string | null | undefined): string {
    if (!path) return '#';
    const baseUrl = String(this.apiUrl).replace('/api', '');
    return `${baseUrl}/storage/${path}`;
  }

  // Activar el modo edición
public enableEditing(): void {
    const req = this.currentRequirement();
    
    // FAILSAFE HARD GATE: Abortamos la edición si está sellado o no está en fase 'RC'
    if (req?.is_locked || req?.status !== 'RC') {
      console.warn('Operación denegada: El requerimiento está sellado o fuera de fase RC.');
      return; 
    }

    this.isEditing.set(true);
    this.editForm.enable(); // Solo habilitamos los campos si pasa la validación de seguridad
  }

  // Cancelar y revertir cambios
  cancelEditing(): void {
    this.isEditing.set(false);
    this.loadRequirementDetails(); // Recargamos los datos originales
  }

// Persistir cambios en el Backend
  saveChanges(): void {
    if (this.editForm.invalid) return;

    this.isLoading.set(true);
    const id = this.requirementId();
    if (!id) return;

    const rawData = this.editForm.getRawValue();

    // 🟢 FIX: Construimos el FormData que exige tu servicio
    const payload = new FormData();

    payload.append('_method', 'PUT');
    
    // Agregamos los campos de texto simples
    payload.append('rrti', rawData.rrti);
    payload.append('requirement_type', rawData.requirement_type);
    payload.append('management_type', rawData.management_type);
    payload.append('creation_date', rawData.creation_date);
    payload.append('description', rawData.description || '');
    payload.append('persona_id', rawData.persona_id);

    // 🟢 Manejo especial para Arrays en FormData (para que Laravel los entienda)
    if (rawData.cspe_consultants && rawData.cspe_consultants.length > 0) {
      rawData.cspe_consultants.forEach((cspeId: string) => {
        // Al agregar '[]', Laravel lo recibe automáticamente como un Array
        payload.append('cspe_consultants[]', cspeId);
      });
    }

    // Inyectamos los archivos si el usuario subió unos nuevos
    if (this.newFiles.itReq) {
      payload.append('it_request_doc', this.newFiles.itReq);
    }
    if (this.newFiles.needsSpread) {
      payload.append('needs_spreadsheet', this.newFiles.needsSpread);
    }

    this.requirementService.updateRequirement(id, payload).subscribe({
      next: () => {
        this.isEditing.set(false);
        this.loadRequirementDetails(); 

        this.requirementService.refreshDashboard$.next();
        
        // Sweetalert: Éxito
        Swal.fire({
          title: '¡Actualización Exitosa!',
          text: 'Los cambios del requerimiento han sido guardados correctamente.',
          icon: 'success',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#aa00ff', // Morado corporativo CANTV
          customClass: {
            popup: 'rounded-4'
          }
        });
      },
      error: (err) => {
        this.isLoading.set(false);
        console.error('Detalle del error:', err);

        // 🔍 Intentamos extraer el mensaje exacto del backend (ej: validaciones 422)
        let errorMsg = 'No se pudo actualizar el requerimiento. Por favor, verifica los datos e intenta nuevamente.';
        if (err.status === 422 && err.error && err.error.message) {
            errorMsg = err.error.message;
        }

        // Sweetalert: Error
        Swal.fire({
          title: 'Error al Guardar',
          text: errorMsg,
          icon: 'error',
          confirmButtonText: 'Cerrar',
          confirmButtonColor: '#dc3545', // Rojo peligro estándar
          customClass: {
            popup: 'rounded-4'
          }
        });
      }
    });
  }

  // Helper para manejar los checkboxes CSPE en modo edición
  onCspeChange(cspeId: string, event: Event): void {
    const isChecked = (event.target as HTMLInputElement).checked;
    
    // Extracción segura. Si es undefined, usamos un arreglo vacío []
    const safeValues = this.editForm.get('cspe_consultants')?.value || [];
    const currentCspe = [...safeValues];

    if (isChecked) {
      currentCspe.push(cspeId);
    } else {
      const index = currentCspe.indexOf(cspeId);
      if (index > -1) currentCspe.splice(index, 1);
    }

    this.editForm.get('cspe_consultants')?.setValue(currentCspe);
  }

// Manejo de Archivos
  onFileChange(event: Event, type: 'itReq' | 'needsSpread'): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
      this.newFiles[type] = file;
    }
  }

  public onViewDocument(path: string): void {
  this.requirementService.downloadPrivateDocument(path);
}

  public openPrivateDoc(path: string | null | undefined): void {
    if (!path) return;
    this.requirementService.downloadPrivateDocument(path);
  }

// Escuchamos los cambios en el selector (consultor funcional)en tiempo real
  private listenToConsultantChanges(): void {
    // Escuchamos los cambios en el selector reactivo del formulario
    this.editForm.get('persona_id')?.valueChanges
      .pipe(takeUntil(this.destroy$))
      .subscribe((newPersonaId) => {
        if (newPersonaId) {
          // 🟢 TypeScript ahora reconoce perfectamente a 'c' como FunctionalConsultantItem
          const selectedConsultant = this.functionalConsultants().find(
            c => c.id === newPersonaId || c.persona_id === newPersonaId
          );
          
          if (selectedConsultant) {
            this.organizationalData.update(currentData => ({
              ...currentData, // Propagamos los datos existentes
              society_name: selectedConsultant.society_name || selectedConsultant.society?.name || 'N/A',
              system_name: selectedConsultant.system_name || selectedConsultant.system?.name || 'N/A',
              requesting_unit_name: selectedConsultant.unit_name || selectedConsultant.requesting_unit?.name || 'N/A',
              persona_id: newPersonaId,
              functional_consultant_id: selectedConsultant.functional_consultant_id || selectedConsultant.id
            }));
          }
        }
      });
  }

  // ----------------------------------------------------------------------
  // VALIDACIÓN DE OPERACIONES ESPECIALES
  // ----------------------------------------------------------------------
  async iniciarBorradoLogico() {
    const { value: pin } = await Swal.fire({
      title: 'Operación Crítica',
      text: 'Ingrese su clave especial de operaciones:',
      input: 'password',
      inputAttributes: {
        autocapitalize: 'off',
        autocorrect: 'off'
      },
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Validar PIN',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#0d6efd',
    });

    if (!pin) return; // Si el usuario cancela

    this.isLoading.set(true);

    // POST /deletion-ticket (Usamos TU método exacto)
    this.requirementService.requestDeletionTicket(pin).subscribe({
      next: (response: DeletionTicketResponse) => {
        this.isLoading.set(false);
        const ticketString = response.data.deletion_ticket; 
        this.solicitarMotivoBorrado(ticketString);
      },
      error: (err) => {
        this.isLoading.set(false);
        
        // Atrapamos el código de estado que nos envía Laravel
        const status = err.status;
        const serverMessage = err.error?.message || 'Ha ocurrido un error inesperado.';

        switch (status) {
          case 428: // PRECONDITION REQUIRED: No tiene PIN configurado
            Swal.fire({
              title: '¡Bienvenido a Operaciones Especiales!',
              text: 'Para continuar, primero debes configurar tu PIN de alta seguridad.',
              icon: 'info',
              confirmButtonText: 'Configurar PIN ahora',
              confirmButtonColor: '#0d6efd'
            }).then((result) => {
              if (result.isConfirmed) {
                this.abrirModalDeConfiguracionPin(); //  flujo de configuración
              }
            });
            break;

          case 426: // UPGRADE REQUIRED: El PIN caducó
            Swal.fire({
              title: 'PIN Expirado',
              text: 'Por políticas de seguridad, tu PIN ha caducado (90 días). Por favor, actualízalo.',
              icon: 'warning',
              confirmButtonText: 'Actualizar PIN',
              confirmButtonColor: '#ffc107'
            }).then((result) => {
              if (result.isConfirmed) {
                this.abrirModalDeConfiguracionPin(); 
              }
            });
            break;

          case 423: // LOCKED: Bloqueado por fuerza bruta
            Swal.fire('Cuenta Bloqueada', serverMessage, 'error');
            break;

          case 401: // UNAUTHORIZED: PIN incorrecto
            Swal.fire('Acceso Denegado', serverMessage, 'error');
            break;

          default:
            Swal.fire('Error', serverMessage, 'error');
            break;
        }
      }
    });
  }


  // ----------------------------------------------------------------------
  // FLUJO DE CONFIGURACIÓN DE PIN (AUTOSERVICIO CON SWEETALERT)
  // ----------------------------------------------------------------------
  async abrirModalDeConfiguracionPin() {
    const { value: formValues } = await Swal.fire({
      title: 'Configuración de Seguridad',
      html: `
        <p class="text-muted" style="font-size: 0.9em; margin-bottom: 15px;">
          Verifica tu identidad y define un PIN numérico (4 a 6 dígitos) para autorizar operaciones críticas.
        </p>
        <div class="text-start mb-3">
          <label for="swal-login-password" class="fw-bold mb-1" style="font-size: 0.9em;">Contraseña de Inicio de Sesión</label>
          <input type="password" id="swal-login-password" class="swal2-input m-0 w-100" placeholder="Tu contraseña actual">
        </div>
        <div class="text-start">
          <label for="swal-new-pin" class="fw-bold mb-1" style="font-size: 0.9em;">Nuevo PIN de Operaciones</label>
          <input type="password" id="swal-new-pin" class="swal2-input m-0 w-100" placeholder="Ej. 123456" maxlength="6" inputmode="numeric">
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Guardar PIN',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#0d6efd',
      customClass: { popup: 'rounded-4' },
      preConfirm: () => {
        // Capturamos los valores del DOM de SweetAlert
        const password = (document.getElementById('swal-login-password') as HTMLInputElement).value;
        const pin = (document.getElementById('swal-new-pin') as HTMLInputElement).value;

        // Validaciones en caliente
        if (!password) {
          Swal.showValidationMessage('Debes ingresar tu contraseña actual.');
          return false;
        }
        if (!pin || pin.length < 4 || pin.length > 6 || !/^\d+$/.test(pin)) {
          Swal.showValidationMessage('El PIN debe contener entre 4 y 6 números.');
          return false;
        }

        // Retornamos el objeto si todo es válido
        return { password, pin };
      }
    });

    // Si el usuario presionó "Guardar PIN" y pasó las validaciones front-end
    if (formValues) {
      this.isLoading.set(true);

      // Disparamos la petición a Laravel
      this.requirementService.setupSpecialPin(formValues.password, formValues.pin).subscribe({
        next: (res: {success: boolean, message: string}) => {
          this.isLoading.set(false);
          
          Swal.fire({
            title: '¡Bóveda Asegurada!',
            text: res.message,
            icon: 'success',
            customClass: { popup: 'rounded-4' }
          }).then(() => {
            // Opcional y muy elegante: Una vez configurado el PIN, 
            // le relanzamos automáticamente el modal de borrado para que no pierda el hilo.
            this.iniciarBorradoLogico();
          });
        },
        error: (err) => {
          this.isLoading.set(false);
          // Laravel nos dirá si la contraseña es incorrecta o si el PIN es igual a la clave
          const errorMessage = err.error?.message || 'No se pudo configurar el PIN de seguridad.';
          Swal.fire('Error de Configuración', errorMessage, 'error');
        }
      });
    }
  }

  // ----------------------------------------------------------------------
  //BORRADO LÓGICO Y AUDITORÍA
  // ----------------------------------------------------------------------
  async solicitarMotivoBorrado(ticketValido: string) {
    const id = this.requirementId();
    if (!id) return;

    const { value: motivo } = await Swal.fire({
      title: 'Justificación Requerida',
      input: 'textarea',
      inputLabel: 'Indique el motivo de la eliminación (Mínimo 10 caracteres)',
      inputPlaceholder: 'Escriba aquí la justificación...',
      showCancelButton: true,
      confirmButtonText: 'Confirmar Eliminación',
      confirmButtonColor: '#dc3545',
      preConfirm: (text) => {
        if (!text || text.trim().length < 10) {
          Swal.showValidationMessage('El motivo debe tener al menos 10 caracteres');
        }
        return text;
      }
    });

    if (motivo) {
      this.isLoading.set(true);

      // DELETE /requirements/{id} (Usamos TU método pasándole el ticket y el motivo)
      this.requirementService.softDeleteRequirement(id, ticketValido, motivo).subscribe({
        next: (res) => {
          this.isLoading.set(false);
          
          // 1. Avisamos al Dashboard que recargue (usando nuestra alarma de RxJS)
          this.requirementService.refreshDashboard$.next();
          
          // 2. Éxito y cierre
          Swal.fire({
            title: '¡Eliminado!', 
            text: res.message || 'El requerimiento ha sido eliminado lógicamente.', 
            icon: 'success',
            customClass: { popup: 'rounded-4' }
          }).then(() => {
            // Inyecta private router: Router en tu constructor si no lo tienes
            this.router.navigate(['/dashboard']); 
          });
        },
        error: (err) => {
          this.isLoading.set(false);
          let errorMsg = 'No tienes permisos para realizar esta operación.';
          
          // Si el TTL expiró, usualmente tu backend mandará un 403 o 422
          if (err.status === 403 || err.status === 422) {
            errorMsg = 'El ticket de eliminación ha expirado (Tiempo superado). Intente nuevamente.';
          }
          
          Swal.fire('Error al eliminar', errorMsg, 'error');
        }
      });
    }
  }
}