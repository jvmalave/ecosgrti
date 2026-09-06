import { Component, inject, signal, computed, output, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, NonNullableFormBuilder, Validators } from '@angular/forms';
import { Subject, forkJoin } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import Swal, { SweetAlertIcon } from 'sweetalert2';
import { RequirementService } from '../../data-access/services/requirement.service';
import { 
  OrganizationalGraph, 
  CatalogItem, 
  FunctionalConsultantItem, 
  CspeConsultantItem 
} from '../../data-access/models/requirement.model';

@Component({
  selector: 'lib-requirement-create-modal', // Selector actualizado para reflejar su nueva naturaleza
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './requirement-create.component.html',
  styleUrls: ['./requirement-create.component.scss'],
})
export class RequirementCreateComponent implements OnInit, OnDestroy {
  
  private fb = inject(NonNullableFormBuilder);
  private requirementService = inject(RequirementService);
  private destroy$ = new Subject<void>();

  // ==========================================
  // 1. SALIDAS (Outputs hacia el Dashboard)
  // ==========================================
  closeModal = output<void>();
  requirementCreated = output<void>();

  // ==========================================
  // 2. ESTADO LOCAL Y DATOS
  // ==========================================
  isLoading = signal<boolean>(false);
  isLoadingData = signal<boolean>(true);
  organizationalData = signal<OrganizationalGraph | null>(null);

  requirementTypes = signal<CatalogItem[]>([]);
  managementTypes = signal<CatalogItem[]>([]);
  cspeConsultants = signal<CspeConsultantItem[]>([]);
  functionalConsultants = signal<FunctionalConsultantItem[]>([]);

  // Buscador Reactivo para Consultores Funcionales
  searchTerm = signal<string>('');
  isDropdownOpen = signal<boolean>(false);
  filteredConsultants = computed(() => {
    const term = this.searchTerm().toLowerCase();
    return this.functionalConsultants().filter(c => 
      c.full_name.toLowerCase().includes(term)
    );
  });

  solicitudTiFile: File | null = null;
  planillaNecesidadesFile: File | null = null;

  requirementForm = this.fb.group({
    rrti: ['', [Validators.required, Validators.pattern('^[0-9]+$')]],
    requirement_type: ['', Validators.required],
    management_type: ['', Validators.required],
    creation_date: ['', [Validators.required]],
    description: ['', [Validators.required, Validators.minLength(10)]],
    persona_id: ['', [Validators.required]], 
    cspe_consultants: [[] as string[], Validators.required] 
  });

  ngOnInit(): void {
    this.loadDictionaries();

    this.requirementForm.get('persona_id')?.valueChanges
      .pipe(takeUntil(this.destroy$))
      .subscribe(personaId => {
        if (personaId) this.executeOrganizationalLookup(personaId);
        else this.organizationalData.set(null);
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ==========================================
  // 3. LÓGICA DE INTERFAZ Y NOTIFICACIONES
  // ==========================================
  private showNotification(icon: SweetAlertIcon, title: string, html: string, showCancel = false) {
    return Swal.fire({
      icon,
      title,
      html,
      showCancelButton: showCancel,
      confirmButtonText: showCancel ? 'Sí, continuar' : 'Aceptar',
      cancelButtonText: 'No, regresar',
      confirmButtonColor: '#8e1482' // Ajustado al color de marca de ECOSGRTI
    });
  }

  private loadDictionaries(): void {
    this.isLoadingData.set(true);
    forkJoin({
      reqTypes: this.requirementService.getRequirementTypes(),
      manTypes: this.requirementService.getManagementTypes(),
      funcConsultants: this.requirementService.getFunctionalConsultants(),
      cspe: this.requirementService.getCspeConsultants()
    }).subscribe({
      next: (responses) => {
        this.requirementTypes.set(responses.reqTypes.data);
        this.managementTypes.set(responses.manTypes.data);
        this.functionalConsultants.set(responses.funcConsultants.data);
        this.cspeConsultants.set(responses.cspe.data);
        this.isLoadingData.set(false);
      },
      error: () => {
        this.isLoadingData.set(false);
        this.showNotification('error', 'Error de Conexión', 'No se pudieron cargar los catálogos.');
      }
    });
  }

  private executeOrganizationalLookup(personaId: string): void {
    this.isLoading.set(true);
    this.requirementService.getOrganizationalLookup(personaId).subscribe({
      next: (response) => {
        this.organizationalData.set(response.data);
        this.isLoading.set(false);
      },
      error: () => {
        this.organizationalData.set(null);
        this.isLoading.set(false);
        this.showNotification('warning', 'Atención', 'No se pudo resolver la jerarquía de este consultor.');
      }
    });
  }

  // ==========================================
  // 4. MANEJO DE EVENTOS DEL FORMULARIO
  // ==========================================
  onSearchConsultant(event: Event): void {
    this.searchTerm.set((event.target as HTMLInputElement).value);
    this.isDropdownOpen.set(true); 
    this.requirementForm.get('persona_id')?.setValue('');
  }

  selectConsultant(personaId: string): void {
    this.requirementForm.get('persona_id')?.setValue(personaId);
    this.searchTerm.set(''); 
    this.isDropdownOpen.set(false); 
  }

  getSelectedConsultantName(): string {
    const id = this.requirementForm.get('persona_id')?.value;
    const consultant = this.functionalConsultants().find(c => c.persona_id === id);
    return consultant ? consultant.full_name : '';
  }

  onCspeChange(event: Event, cspeId: string): void {
    const isChecked = (event.target as HTMLInputElement).checked;
    const currentValues = this.requirementForm.get('cspe_consultants')?.value as string[];
    
    if (isChecked) {
      this.requirementForm.get('cspe_consultants')?.setValue([...currentValues, cspeId]);
    } else {
      this.requirementForm.get('cspe_consultants')?.setValue(currentValues.filter(id => id !== cspeId));
    }
  }

  isCspeSelected(cspeId: string): boolean {
    const currentValues = this.requirementForm.get('cspe_consultants')?.value as string[] || [];
    return currentValues.includes(cspeId);
  }

  onFileSelected(event: Event, fileType: 'solicitud' | 'planilla'): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      if (fileType === 'solicitud') this.solicitudTiFile = input.files[0];
      if (fileType === 'planilla') this.planillaNecesidadesFile = input.files[0];
    }
  }

  // ==========================================
  // 5. ACCIONES PRINCIPALES (Guardar / Cancelar)
  // ==========================================
  onCancel(): void {
    // Verificamos si el usuario ingresó algún dato antes de advertirle
    if (this.requirementForm.dirty || this.solicitudTiFile || this.planillaNecesidadesFile) {
      this.showNotification('warning', '¿Descartar registro?', 'Se perderán todos los datos ingresados.', true)
        .then((result) => {
          if (result.isConfirmed) {
            this.closeModal.emit(); // Emitimos el cierre y dejamos que Angular limpie la memoria
          }
        });
    } else {
      this.closeModal.emit(); // Cerramos directo si no tocó nada
    }
  }

  onSubmit(): void {
    if (this.requirementForm.invalid || !this.solicitudTiFile || !this.planillaNecesidadesFile) {
      this.requirementForm.markAllAsTouched();
      this.showNotification('warning', 'Formulario Incompleto', 'Debe llenar todos los campos obligatorios y adjuntar los archivos requeridos.');
      return;
    }

    this.isLoading.set(true);
    const formValues = this.requirementForm.getRawValue();
    const formData = new FormData();

    formData.append('rrti', formValues.rrti);
    formData.append('requirement_type', formValues.requirement_type);
    formData.append('management_type', formValues.management_type);
    formData.append('creation_date', formValues.creation_date);
    formData.append('description', formValues.description);
    formData.append('functional_consultant_id', formValues.persona_id);
    
    formValues.cspe_consultants.forEach((id: string, index: number) => {
      formData.append(`cspe_consultants[${index}]`, id);
    });

    formData.append('it_request_doc', this.solicitudTiFile);
    formData.append('needs_spreadsheet', this.planillaNecesidadesFile);

    this.requirementService.createRequirement(formData).subscribe({
      next: () => {
        this.isLoading.set(false);
        this.showNotification('success', 'Requerimiento Registrado', 'El requerimiento se ha creado exitosamente en el ecosistema.')
          .then(() => {
            this.requirementCreated.emit(); // Le avisamos al Dashboard que recargue la tabla
            this.closeModal.emit(); // Cerramos el modal
          });
      },
      error: (err) => {
        this.isLoading.set(false);
        let errorMsg = 'Ocurrió un error en el servidor. Consulte los logs.';
        
        if (err.status === 422 && err.error?.errors) {
          const backendErrors = Object.values(err.error.errors).flat().join('<br>• ');
          errorMsg = `<div class="text-start">Verifique los siguientes datos:<br><br>• ${backendErrors}</div>`;
        } else if (err.error?.message) {
          errorMsg = err.error.message;
        }

        this.showNotification('error', 'Error de Validación', errorMsg);
      }
    });
  }
}