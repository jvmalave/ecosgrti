// apps/frontend/src/app/domains/security/features/mdm/unified-person-list-modal/unified-person-list-modal.component.ts

import { Component, OnInit, OnDestroy, inject, signal, computed, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormControl } from '@angular/forms';
import { Subject, takeUntil, debounceTime, distinctUntilChanged } from 'rxjs';
import { UnifiedPersonService } from '../../../lib/data-access/services/unified-person.service';
import { UnifiedPerson } from '../../../lib/data-access/models/unified-person.model';
import { NotificationService } from '@ecosgrti/workflow';
import { UnifiedPersonModalComponent } from '../unified-person-modal/unified-person-modal.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'lib-unified-person-list-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, UnifiedPersonModalComponent],
  templateUrl: './unified-person-list-modal.component.html',
  styleUrls: ['./unified-person-list-modal.component.scss']
})
export class UnifiedPersonListModalComponent implements OnInit, OnDestroy {
  private personService = inject(UnifiedPersonService);
  private notificationService = inject(NotificationService);
  private destroy$ = new Subject<void>();

  @Output() modalClosed = new EventEmitter<void>();

  // Signals de estado
  public identities = signal<UnifiedPerson[]>([]);
  public isLoading = signal<boolean>(false);
  public currentPage = signal<number>(1);
  public totalPages = signal<number>(1);

  // Control de visibilidad para el sub-modal de creación/edición
  public showFormModal = signal<boolean>(false);
  public selectedPersonId = signal<string | null>(null);

  // Control de búsqueda reactiva (Idéntico patrón al Dashboard)
  public searchControl = new FormControl('');

  // Signal Computada (Filtra de forma inmediata los elementos cargados o actúa como espejo)
  public filteredIdentities = computed(() => {
    return this.identities();
  });

  ngOnInit(): void {
    this.loadIdentities();

    // Suscripción reactiva idéntica a la del Dashboard con debounce de 500ms
    this.searchControl.valueChanges.pipe(
      debounceTime(500),
      distinctUntilChanged(),
      takeUntil(this.destroy$)
    ).subscribe(searchTerm => {
      this.currentPage.set(1);
      this.loadIdentities(1, searchTerm || undefined);
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  public loadIdentities(page = 1, search?: string): void {
    this.isLoading.set(true);
    const term = search !== undefined ? search : (this.searchControl.value || undefined);

    // Ajusta la llamada al servicio pasando el término de búsqueda si tu API lo soporta, 
    // o realiza el filtro sobre la respuesta.
    this.personService.getIdentities(page).subscribe({
      next: (response) => {
        let data = response.data || [];
        
        // Si el backend no filtra por query directamente, aplicamos el filtro reactivo sobre los datos obtenidos
        if (term) {
          const query = term.toLowerCase().trim();
          data = data.filter(person => 
            (person.first_name && person.first_name.toLowerCase().includes(query)) ||
            (person.last_name && person.last_name.toLowerCase().includes(query)) ||
            (person.email && person.email.toLowerCase().includes(query))
          );
        }

        this.identities.set(data);
        this.currentPage.set(response.meta?.current_page || 1);
        this.totalPages.set(response.meta?.last_page || 1);
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.notificationService.showError('Error de Conexión', 'No se pudo cargar el listado de identidades.');
      }
    });
  }

  public openCreateModal(): void {
    this.selectedPersonId.set(null);
    this.showFormModal.set(true);
  }

  public openEditModal(id: string): void {
    this.selectedPersonId.set(id);
    this.showFormModal.set(true);
  }

  public closeFormModal(): void {
    this.showFormModal.set(false);
    this.selectedPersonId.set(null);
    this.loadIdentities(this.currentPage());
  }

  public onDelete(person: UnifiedPerson): void {
    Swal.fire({
      title: '¿Estás seguro?',
      text: `Se inhabilitará la ficha unificada de ${person.first_name} ${person.last_name}.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sí, desactivar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.personService.deleteUnifiedPerson(person.id).subscribe({
          next: () => {
            this.notificationService.showSuccess('Desactivado', 'La ficha unificada ha sido inhabilitada.');
            this.loadIdentities(this.currentPage());
          },
          error: () => {
            this.notificationService.showError('Error', 'No se pudo procesar la desactivación lógica.');
          }
        });
      }
    });
  }

  public onClose(): void {
    this.modalClosed.emit();
  }

  public changePage(page: number): void {
    if (page >= 1 && page <= this.totalPages()) {
      this.loadIdentities(page);
    }
  }
}