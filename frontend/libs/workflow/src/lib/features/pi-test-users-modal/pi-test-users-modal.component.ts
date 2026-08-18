import { Component, OnInit, inject, input, output, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { WorkflowPhaseService } from '../../data-access/services/workflow-phase.service';
import { NotificationService } from '../../data-access/services/notification.services';
import Swal from 'sweetalert2';
import { PiTestUserItem } from '../../data-access/models/workflow-phase.models';

@Component({
  selector: 'lib-pi-test-users-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './pi-test-users-modal.component.html',
  styleUrls: ['./pi-test-users-modal.component.scss']
})
export class PiTestUsersModalComponent implements OnInit {
  private phaseService = inject(WorkflowPhaseService);
  private notificationService = inject(NotificationService);
  private fb = inject(FormBuilder);

  // Inputs & Outputs
  requirementId = input.required<string>();
  roleId = input.required<string>();
  roleName = input.required<string>();
  rrti = input.required<string>();
  closeModal = output<void>();

  // Estado Reactivo
  testUsers = signal<PiTestUserItem[]>([]);
  isLoading = signal<boolean>(true);
  isSubmitting = signal<boolean>(false);
  searchQuery = signal<string>('');

  userForm: FormGroup;

  // Filtrado Reactivo en Cliente (RN-PI-27 y RN-PI-28)
  filteredUsers = computed(() => {
    const query = this.searchQuery().toLowerCase();
    return this.testUsers().filter(user => 
      user.identifier.toLowerCase().includes(query)
    );
  });

  constructor() {
    this.userForm = this.fb.group({
      identifier: ['', [Validators.required, Validators.maxLength(50)]]
    });
  }

  ngOnInit(): void {
    this.loadUsers();
  }

  loadUsers(): void {
    this.isLoading.set(true);
    this.phaseService.getPiTestUsers(this.roleId()).subscribe({
      next: (res) => {
        this.testUsers.set(res.data);
        this.isLoading.set(false);
      },
      error: () => {
        this.notificationService.showError('Error', 'No se pudieron cargar los usuarios.');
        this.isLoading.set(false);
      }
    });
  }

  updateSearch(event: Event): void {
    this.searchQuery.set((event.target as HTMLInputElement).value);
  }

  // 🟢 FLUJO TWO-STEP SUBMISSION
  saveUser(force = false): void {
    if (this.userForm.invalid) return;

    this.isSubmitting.set(true);
    const identifier = this.userForm.value.identifier;

    this.phaseService.addPiTestUser(this.roleId(), this.requirementId(), identifier, force)
      .subscribe({
        next: () => {
          this.notificationService.toastSuccess('Usuario agregado correctamente');
          this.userForm.reset();
          this.loadUsers();
          this.isSubmitting.set(false);
        },
        error: (err: HttpErrorResponse) => {
          this.isSubmitting.set(false);

          // RN-PI-22: Capturamos el 409 Conflict para la confirmación de 2 pasos
          if (err.status === 409 && err.error?.requires_confirmation) {
            const reqsStr = err.error.reqs.join(', ');
            
            Swal.fire({
              title: 'Usuario Múltiple',
              html: `El usuario <b>${identifier}</b> se encuentra asignado actualmente en los requerimientos activos: <br><br> <b>${reqsStr}</b> <br><br> ¿Deseas incorporarlo también a este proyecto?`,
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, incorporar',
              cancelButtonText: 'Cancelar',
              confirmButtonColor: '#d500f9'
            }).then((result) => {
              if (result.isConfirmed) {
                // Re-ejecutamos con la bandera force = true
                this.saveUser(true);
              }
            });
          } else if (err.status === 422) {
            this.notificationService.showWarning('Duplicado', err.error.message);
          } else {
            this.notificationService.showError('Error', 'Hubo un problema al guardar el usuario.');
          }
        }
      });
  }

  async deleteUser(userId: string, identifier: string): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      'Eliminar Usuario',
      `¿Estás seguro que deseas remover a ${identifier} de este rol?`
    );

    if (isConfirmed) {
      this.phaseService.deletePiTestUser(userId).subscribe({
        next: () => {
          this.notificationService.toastSuccess('Usuario eliminado');
          this.loadUsers();
        },
        error: (err: HttpErrorResponse) => {
          if (err.status === 422) {
            this.notificationService.showError('Bloqueo de Sistema', err.error.message);
          } else {
            this.notificationService.showError('Error', 'No se pudo eliminar el usuario.');
          }
        }
      });
    }
  }
}