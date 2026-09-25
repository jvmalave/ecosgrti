import { Component, OnInit, inject, signal, computed, DestroyRef, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { OrgStructureService } from '../../data-access/services/org-structure.service';
import { Society, SystemNode, RequestingUnit } from '../../data-access/models/org-structure.model';
import { NotificationService } from '@ecosgrti/workflow'; 

@Component({
  selector: 'lib-org-structure',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './org-structure.component.html',
  styleUrls: ['./org-structure.component.scss']
})
export class OrgStructureComponent implements OnInit {
  @Output() modalClosed = new EventEmitter<void>();

  private readonly orgService = inject(OrgStructureService);
  private readonly notificationService = inject(NotificationService);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);

  // ==========================================
  // ESTADOS MAESTROS (Signals)
  // ==========================================
  societiesMaster = signal<Society[]>([]);
  systemsMaster = signal<SystemNode[]>([]);
  unitsMaster = signal<RequestingUnit[]>([]);
  
  isLoading = signal<boolean>(false);
  selectedSocietyId = signal<string | null>(null);

  // ==========================================
  // CONTROL DE MODALES Y EDICIÓN
  // ==========================================
  isAddSocietyModalOpen = signal<boolean>(false);
  isAddSystemModalOpen = signal<boolean>(false);
  isAddUnitModalOpen = signal<boolean>(false);

  // Rastrea si estamos creando (null) o editando (UUID)
  editingNodeId = signal<string | null>(null);

  // ==========================================
  // FORMULARIOS REACTIVOS
  // ==========================================
  societyForm: FormGroup = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(150)]],
    acronym: ['', [Validators.maxLength(20)]]
  });

  systemForm: FormGroup = this.fb.group({
    society_id: ['', Validators.required],
    name: ['', [Validators.required, Validators.maxLength(150)]]
  });

  unitForm: FormGroup = this.fb.group({
    society_id: ['', Validators.required], // Auxiliar para el filtrado en cascada
    system_id: ['', Validators.required],
    name: ['', [Validators.required, Validators.maxLength(150)]]
  });

  // Filtrado en cascada para Sistemas en el modal de Unidades
  sistemasFiltrados = computed(() => {
    const societyId = this.selectedSocietyId();
    if (!societyId) return [];
    return this.systemsMaster().filter(s => s.society_id === societyId && s.is_active);
  });

  ngOnInit(): void {
    this.loadOrgTree();
    this.orgService.refresh$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.loadOrgTree());
  }

  private loadOrgTree(): void {
    this.isLoading.set(true);
    this.orgService.getOrgTree()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (response && response.data) {
            this.societiesMaster.set(response.data.societies || []);
            this.systemsMaster.set(response.data.systems || []);
            this.unitsMaster.set(response.data.requesting_units || []);
          }
          this.isLoading.set(false);
        },
        error: (err) => {
          this.isLoading.set(false);
          console.error('Error al cargar árbol organizacional:', err);

          // Evalua si el backend nos rechazó por permisos (403)
          if (err.status === 403) {
            // Extraemos el mensaje real de Laravel o usamos uno por defecto
            const forbiddenMessage = err.error?.message || 'No tiene permisos para ver esta estructura.';
            this.notificationService.showError('Acceso Denegado', forbiddenMessage);
          } else {
            // Para otros errores (500, 404, etc.) mantenemos el mensaje genérico
            this.notificationService.showError('Error de API', 'No se pudo cargar el árbol organizacional.');
          }
        }
      });
  }
  closeMainModal(): void {
    this.modalClosed.emit();
  }

  // ==========================================
  // MÉTODOS DE APERTURA DE MODALES (CREAR/EDITAR)
  // ==========================================

  // SOCIEDAD
  openSocietyModal(society?: Society): void {
    if (society) {
      this.editingNodeId.set(society.id);
      this.societyForm.patchValue({ name: society.name, acronym: society.acronym });
    } else {
      this.editingNodeId.set(null);
      this.societyForm.reset();
    }
    this.isAddSocietyModalOpen.set(true);
  }

  closeSocietyModal(): void {
    this.isAddSocietyModalOpen.set(false);
    this.editingNodeId.set(null);
  }

  // SISTEMA
  openSystemModal(system?: SystemNode): void {
    if (system) {
      this.editingNodeId.set(system.id);
      this.systemForm.patchValue({ society_id: system.society_id, name: system.name });
    } else {
      this.editingNodeId.set(null);
      this.systemForm.reset({ society_id: '', name: '' });
    }
    this.isAddSystemModalOpen.set(true);
  }

  closeSystemModal(): void {
    this.isAddSystemModalOpen.set(false);
    this.editingNodeId.set(null);
  }

  // UNIDAD SOLICITANTE
  openUnitModal(unit?: RequestingUnit): void {
    if (unit) {
      this.editingNodeId.set(unit.id);
      // Buscamos la sociedad padre para el filtrado en cascada
      const parentSystem = this.systemsMaster().find(s => s.id === unit.system_id);
      if (parentSystem) {
        this.selectedSocietyId.set(parentSystem.society_id);
        this.unitForm.patchValue({ 
          society_id: parentSystem.society_id, 
          system_id: unit.system_id, 
          name: unit.name 
        });
      }
    } else {
      this.editingNodeId.set(null);
      this.selectedSocietyId.set(null);
      this.unitForm.reset({ society_id: '', system_id: '', name: '' });
    }
    this.isAddUnitModalOpen.set(true);
  }

  closeUnitModal(): void {
    this.isAddUnitModalOpen.set(false);
    this.editingNodeId.set(null);
  }

  onSocietySelectionChange(event: Event): void {
    const selectElement = event.target as HTMLSelectElement;
    this.selectedSocietyId.set(selectElement.value || null);
    this.unitForm.get('system_id')?.setValue('');
  }

  // ==========================================
  // MÉTODOS DE GUARDADO (POST / PUT)
  // ==========================================

  onSubmitSociety(): void {
    if (this.societyForm.invalid) { this.societyForm.markAllAsTouched(); return; }
    
    const payload = this.societyForm.value;
    const currentId = this.editingNodeId(); // Guardamos el valor para que TS lo infiera con seguridad
    
    const req$ = currentId 
      ? this.orgService.updateSociety(currentId, payload)
      : this.orgService.createSociety(payload);

    req$.subscribe({
      next: (res) => {
        this.notificationService.toastSuccess(res.message || 'Operación exitosa');
        this.orgService.notifyRefresh();
        this.closeSocietyModal();
      },
      error: (err) => this.notificationService.showError('Error', err.error?.message || 'Error al guardar')
    });
  }

  onSubmitSystem(): void {
    if (this.systemForm.invalid) { this.systemForm.markAllAsTouched(); return; }
    
    const payload = { society_id: this.systemForm.value.society_id, name: this.systemForm.value.name };
    const currentId = this.editingNodeId();
    
    const req$ = currentId 
      ? this.orgService.updateSystem(currentId, payload)
      : this.orgService.createSystem(payload);

    req$.subscribe({
      next: (res) => {
        this.notificationService.toastSuccess(res.message || 'Operación exitosa');
        this.orgService.notifyRefresh();
        this.closeSystemModal();
      },
      error: (err) => this.notificationService.showError('Error', err.error?.message || 'Error al guardar')
    });
  }

  onSubmitUnit(): void {
    if (this.unitForm.invalid) { this.unitForm.markAllAsTouched(); return; }

    const payload = { system_id: this.unitForm.value.system_id, name: this.unitForm.value.name };
    const currentId = this.editingNodeId();
    
    const req$ = currentId 
      ? this.orgService.updateRequestingUnit(currentId, payload)
      : this.orgService.createRequestingUnit(payload);

    req$.subscribe({
      next: (res) => {
        this.notificationService.toastSuccess(res.message || 'Operación exitosa');
        this.orgService.notifyRefresh();
        this.closeUnitModal();
      },
      error: (err) => this.notificationService.showError('Error', err.error?.message || 'Error al guardar')
    });
  }

  // ==========================================
  // MÉTODO PARA ALTERNAR ESTATUS (PATCH)
  // ==========================================
  async toggleStatus(nodeType: 'societies' | 'systems' | 'requesting-units', id: string, currentStatus: boolean, name: string): Promise<void> {
    const actionText = currentStatus ? 'Desactivar' : 'Activar';
    
    // Usamos el servicio de notificaciones para confirmar la acción
    const confirmed = await this.notificationService.confirm(
      `¿Desea ${actionText} el registro?`,
      `El registro "${name}" cambiará su estado.`
    );

    if (confirmed) {
      this.orgService.updateNodeStatus(nodeType, id, !currentStatus).subscribe({
        next: (res) => {
          this.notificationService.toastSuccess(res.message || `Registro ${actionText.toLowerCase()}o exitosamente.`);
          this.orgService.notifyRefresh();
        },
        error: (err) => {
          this.notificationService.showError('Error de Integridad', err.error?.message || `No se pudo ${actionText.toLowerCase()} el registro.`);
        }
      });
    }
  }
}