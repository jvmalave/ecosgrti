import { Component, EventEmitter, inject, input, OnInit, Output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RequirementRole } from '../../data-access/models/requirement-role.model';
import { RequirementRoleService } from '../../data-access/services/requirement-role.service';
import { NotificationService } from '../../data-access/services/notitication.services';


@Component({
  selector: 'lib-atf-roles-form-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './atf-roles-form-modal.component.html',
  styleUrls: ['./atf-roles-form-modal.component.scss']
})
export class AtfRolesFormModalComponent implements OnInit {

  
  
  public requirementId = input.required<string>();
  public roleToEdit = input<RequirementRole | null>(null);
  public isPhaseClosed = input<boolean>(false); 

  @Output() closeModal = new EventEmitter<void>();
  @Output() roleSaved = new EventEmitter<void>();

  private fb = inject(FormBuilder);
  
  // 🚀 Inyectamos el servicio real
  private roleService = inject(RequirementRoleService);
  private notificationService = inject(NotificationService);

  public isSubmitting = signal<boolean>(false);
  public isEditMode = signal<boolean>(false); 
  public isViewMode = signal<boolean>(false); 
  
  public roleForm!: FormGroup;

  ngOnInit(): void {
    this.initForm();
    this.checkMode();
  }

  private initForm(): void {
    this.roleForm = this.fb.group({
      role_name: ['', [Validators.required, Validators.maxLength(100)]],
      assignment_type: ['', [Validators.required]],
      description: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(500)]]
    });
  }

  private checkMode(): void {
    const role = this.roleToEdit();
    if (role) {
      this.isEditMode.set(true);
      this.isViewMode.set(true); 
      
      this.roleForm.patchValue({
        role_name: role.role_name,
        assignment_type: role.assignment_type,
        description: role.description
      });
      this.roleForm.disable(); 
    }
  }

  public enableEdit(): void {
    this.isViewMode.set(false);
    this.roleForm.enable();
  }

  // 🚀 MÉTODO ONSUBMIT CONECTADO A LA API REAL
  public onSubmit(): void {
    if (this.roleForm.invalid) {
      this.roleForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    const formData = this.roleForm.value;
    
    // Obtenemos el valor actual del Signal
    const currentRole = this.roleToEdit();

    
    if (currentRole) {
      // Flujo de Actualización (PUT)
      this.roleService.updateRole(currentRole.id, formData).subscribe({
        next: (response) => {
          console.log(response.message);
          this.notificationService.toastSuccess('Rol actualizado exitosamente');
          this.isSubmitting.set(false);
          this.roleSaved.emit(); 
          this.closeModal.emit(); 
        },
        error: (err) => {
          console.error('Error al actualizar el rol', err);
          this.isSubmitting.set(false);
        }
      });
    } else {
      // Flujo de Creación (POST)
      this.roleService.createRole(this.requirementId(), formData).subscribe({
        next: (response) => {
          console.log(response.message);
          this.notificationService.toastSuccess('Rol creado exitosamente');
          this.isSubmitting.set(false);
          this.roleSaved.emit(); 
          this.closeModal.emit(); 
        },
        error: (err) => {
          console.error('Error al registrar el rol', err);
          this.isSubmitting.set(false);
        }
      });
    }
  }
}