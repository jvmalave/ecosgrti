import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { DtWorkflowService } from '../../data-access/services/dt-workflow.service';
import { NotificationService } from '../../data-access/services/notification.services';
import { DtRole, DtRegister, DtRegistersResponse } from '../../data-access/models/dt.model';
import Swal from 'sweetalert2';

@Component({
  selector: 'lib-dt-registers-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule], // <-- Importante inyectar ReactiveFormsModule
  templateUrl: './dt-registers-modal.component.html',
  styleUrls: ['./dt-registers-modal.component.scss']
})
export class DtRegistersModalComponent implements OnInit {
  
  role = input.required<DtRole>();
  rrti = input.required<string>();
  closeModal = output<void>();

  private fb = inject(FormBuilder);
  private dtWorkflowService = inject(DtWorkflowService);
  private notificationService = inject(NotificationService);

  // Estados Reactivos
  registerForm!: FormGroup;
  registers = signal<DtRegister[]>([]);
  isSaving = signal<boolean>(false);
  isLoading = signal<boolean>(true);
  editingRegisterId = signal<string | null>(null);

  ngOnInit(): void {
    this.initForm();
    this.loadRegisters();
  }

  // Inicialización estricta del formulario
  private initForm(): void {
    this.registerForm = this.fb.group({
      title: ['', [Validators.required, Validators.maxLength(255)]],
      date: ['', Validators.required],
      description: ['', Validators.required]
    });
  }

  
  loadRegisters(): void {
    this.dtWorkflowService.getRegisters(this.role().id).subscribe({
      next: (response: DtRegistersResponse) => {
        this.registers.set(response.registros || []);
        this.isLoading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.notificationService.showError('Error', 'No se pudo cargar el historial de la bitácora.');
        this.isLoading.set(false);
        console.error('Fallo al cargar registros:', err.message);
      }
    });
  }


  /**
   * Acción del botón guardar (Ahora maneja Creación y Actualización)
   */
  saveRegister(): void {
    if (this.registerForm.invalid) {
      this.registerForm.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    const formData = this.registerForm.value;
    const currentEditId = this.editingRegisterId();

    if (currentEditId) {
      // MODO ACTUALIZACIÓN
      this.dtWorkflowService.updateRegister(currentEditId, formData).subscribe({
        next: (updatedRegister: DtRegister) => {
          // Actualizamos la fila correspondiente en la tabla localmente
          this.registers.update(current => 
            current.map(r => r.id === currentEditId ? { ...r, ...formData } : r)
          );
          this.cancelEdit(); // Limpia y sale del modo edición
          this.isSaving.set(false);
          this.notificationService.toastSuccess('Registro actualizado correctamente.');
        },
        error: (err: HttpErrorResponse) => {
          this.notificationService.showError('Error', 'No se pudo actualizar el registro.');
          this.isSaving.set(false);
          console.error(err.message);
        }
      });

    } else {
      // MODO CREACIÓN (El código que ya tenías)
      this.dtWorkflowService.storeRegister(this.role().requirement_id, this.role().id, formData).subscribe({
        next: (newRegister: DtRegister) => {
          this.registers.update(current => [newRegister, ...current]);
          this.registerForm.reset();
          this.isSaving.set(false);
          this.notificationService.toastSuccess('Registro guardado exitosamente.');
        },
        error: (err: HttpErrorResponse) => {
          console.log('Error al crear el registro del Rol:', err.message);
          this.notificationService.showError('Error', 'No se pudo crear el registro.');
          this.isSaving.set(false);
        }
      });
    }
  }

  /**
   * Prepara el formulario para EDICIÓN
   */
  editRegister(reg: DtRegister): void {
    this.editingRegisterId.set(reg.id);
    
    // Poblar el formulario con los datos existentes
    this.registerForm.patchValue({
      title: reg.title,
      date: reg.date,
      description: reg.description
    });
  }

  /**
   * Cancela la edición y limpia el formulario
   */
  cancelEdit(): void {
    this.editingRegisterId.set(null);
    this.registerForm.reset();
  }

  /**
   * Elimina un registro de la bitácora (Físico)
   */
  async deleteRegister(reg: DtRegister): Promise<void> {
    const isConfirmed = await this.notificationService.confirm(
      'Eliminar Registro',
      `¿Estás seguro que deseas eliminar el hito "${reg.title}"? Esta acción es irreversible.`
    );

    if (isConfirmed) {
      this.dtWorkflowService.deleteRegister(reg.id).subscribe({
        next: () => {
          // Mutación inmutable: filtramos el registro eliminado de la señal
          this.registers.update(current => current.filter(r => r.id !== reg.id));
          this.notificationService.toastSuccess('Registro eliminado exitosamente.');
          
          // Si estaba editando el mismo registro que acaba de borrar, limpiamos el form
          if (this.editingRegisterId() === reg.id) this.cancelEdit();
        },
        error: (err: HttpErrorResponse) => {
          this.notificationService.showError('Error', 'No se pudo eliminar el registro.');
          console.error(err.message);
        }
      });
    }
  }


  /**
   * Muestra los detalles de un registro en modo Solo Lectura para roles cerrados
   */
  /**
   * Muestra los detalles de un registro en modo Solo Lectura con diseño corporativo
   */
  viewRegister(reg: DtRegister): void {
    // Extraemos solo la porción de la fecha (YYYY-MM-DD) para formatearla limpiamente
    const rawDate = reg.date ? new Date(reg.date) : new Date();
    // Ajuste de zona horaria local (Caracas/GMT-4) mediante toLocaleDateString
    const formattedDate = rawDate.toLocaleDateString('es-VE', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    });

    Swal.fire({
      // Eliminamos el título por defecto y el icono gigante de SweetAlert
      title: '',
      icon: undefined,
      html: `
        <div style="text-align: left; padding: 0.5rem;">
          
          <!-- Cabecera Personalizada -->
          <div style="display: flex; align-items: center; border-bottom: 2px solid #ea80fc; padding-bottom: 12px; margin-bottom: 20px;">
            <i class="fa-solid fa-book-journal-whills" style="font-size: 1.5rem; color: #d500f9; margin-right: 12px;"></i>
            <h5 style="margin: 0; font-weight: 700; color: #333; font-size: 1.25rem;">Detalle del Registro Técnico</h5>
          </div>
          
          <!-- Cuerpo de Datos -->
          <div style="margin-bottom: 16px;">
            <span style="font-size: 0.75rem; color: #6c757d; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">Título del Registro </span>
            <p style="margin: 4px 0 0 0; font-size: 1rem; color: #212529; font-weight: 500;">${reg.title}</p>
          </div>
          
          <div style="margin-bottom: 20px;">
            <span style="font-size: 0.75rem; color: #6c757d; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">Fecha de Creación</span>
            <p style="margin: 4px 0 0 0; font-size: 1rem; color: #212529;"><i class="fa-regular fa-calendar text-muted me-2"></i>${formattedDate}</p>
          </div>
          
          <!-- Caja de Descripción -->
          <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 16px;">
            <span style="font-size: 0.75rem; color: #6c757d; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; display: block; margin-bottom: 8px;">Descripción Detallada</span>
            <div style="font-size: 0.95rem; color: #495057; white-space: pre-wrap; line-height: 1.6; text-align: justify;">${reg.description}</div>
          </div>
          
        </div>
      `,
      width: '600px',
      padding: '1rem',
      showCloseButton: true,
      buttonsStyling: false, // Mantenemos apagado el estilo por defecto
      customClass: {
        popup: 'rounded-4 shadow-lg border-0',
        htmlContainer: 'p-0 m-0'
      },
      // Personaliza el estilo de los botones
      didOpen: () => {
        const confirmBtn = Swal.getConfirmButton();
        if (confirmBtn) {
          // El texto
          confirmBtn.textContent = 'Cerrar Vista';
          
          // Inyecta la identidad visual (Degradado CANTV)
          confirmBtn.style.background = 'linear-gradient(90deg, #aa00ff 0%, #d500f9 100%)';
          confirmBtn.style.color = 'white';
          confirmBtn.style.border = 'none';
          confirmBtn.style.borderRadius = '0.375rem'; // Borde redondeado estándar
          confirmBtn.style.padding = '0.5rem 1.5rem';
          confirmBtn.style.fontWeight = '600';
          confirmBtn.style.boxShadow = '0 4px 12px rgba(213, 0, 249, 0.3)';
          
          // Efecto visual básico al pasar el mouse 
          confirmBtn.onmouseover = () => {
            confirmBtn.style.transform = 'translateY(-1px)';
            confirmBtn.style.boxShadow = '0 6px 15px rgba(213, 0, 249, 0.4)';
          };
          confirmBtn.onmouseout = () => {
            confirmBtn.style.transform = 'none';
            confirmBtn.style.boxShadow = '0 4px 12px rgba(213, 0, 249, 0.3)';
          };
        }
      }
    });
  }
}