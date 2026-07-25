import { Component, computed, inject, input, OnInit, signal, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RequirementRole } from '../../data-access/models/requirement-role.model'; 
import { AtfRolesFormModalComponent } from '../atf-roles-form-modal/atf-roles-form-modal.component';
// 🚀 Importamos el servicio
import { RequirementRoleService } from '../../data-access/services/requirement-role.service';
import { NotificationService } from '../../data-access/services/notitication.services';

@Component({
  selector: 'lib-atf-roles-list',
  standalone: true,
  imports: [CommonModule, AtfRolesFormModalComponent],
  templateUrl: './atf-roles-list.component.html',
  styleUrls: ['./atf-roles-list.component.scss']
})
export class AtfRolesListComponent implements OnInit {
  public requirementId = input.required<string>();
  public isPhaseClosed = input<boolean>(false); 

  // 🚀 Inyectamos el servicio real
  private roleService = inject(RequirementRoleService);
  private notificationService = inject(NotificationService);

  public roles = signal<RequirementRole[]>([]);
  public isLoading = signal<boolean>(true);
  public searchTerm = signal<string>('');
  public requirementName = input<string>('Requerimiento');

  public isFormModalOpen = signal<boolean>(false);
  public selectedRole = signal<RequirementRole | null>(null);

  public filteredRoles = computed(() => {
    const term = this.searchTerm().toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    if (!term) return this.roles();
    return this.roles().filter(role => {
      const normalizedName = role.role_name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      return normalizedName.includes(term);
    });
  });

  @Output() closeList = new EventEmitter<void>();

  ngOnInit(): void {
    this.loadRoles();
  }

  // Carga los roles asociados al requerimiento
  public loadRoles(): void {
    this.isLoading.set(true);
    
    this.roleService.getRoles(this.requirementId()).subscribe({
      next: (response) => {
        // TypeScript sabe que response.data es RequirementRole[]
        this.roles.set(response.data);
        this.isLoading.set(false);
      },
      error: (err) => {
        console.error('Error cargando roles', err);
        this.isLoading.set(false);
        this.roles.set([]); 
      }
    });
  }

  public updateSearch(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.searchTerm.set(input.value);
  }

  public openCreateModal(): void {
    this.selectedRole.set(null);
    this.isFormModalOpen.set(true);
  }

  public openViewModal(role: RequirementRole): void {
    this.selectedRole.set(role);
    this.isFormModalOpen.set(true);
  }

  public onModalClose(): void {
    this.isFormModalOpen.set(false);
    this.selectedRole.set(null);
  }

  public onRoleSaved(): void {
    // Al guardar exitosamente, recargamos la lista desde la API
    this.loadRoles();
  }

    public async deleteRole(roleId: string): Promise<void> {
    // 1. Invocamos el modal de confirmación
    const isConfirmed = await this.notificationService.confirm(
      '¿Está seguro?', 
      'Esta acción eliminará el rol técnico de forma permanente.'
    );

    // 2. Evaluamos la respuesta asíncrona
    if (isConfirmed) {
      this.roleService.deleteRole(roleId).subscribe({
        next: (response) => {
          // 3. Notificación de éxito con Título y Mensaje
          this.notificationService.showSuccess(
            'Operación Exitosa', 
            response.message || 'Rol eliminado correctamente'
          );
          
          // 4. Actualizamos el estado del dashboard
          this.loadRoles();
          this.roleService.refreshDashboard$.next(); 
        },
        error: (err) => {
          // 5. Notificación de error con Título y Mensaje
          const errorMsg = err.error?.message || 'No se pudo eliminar el rol. Intente de nuevo.';
          this.notificationService.showError('Error de Procesamiento', errorMsg);
          console.error('Error al eliminar', err);
        }
      });
    }
  }
}