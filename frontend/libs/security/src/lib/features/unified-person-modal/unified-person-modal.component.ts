import { Component, OnInit, inject, signal, effect, Output, EventEmitter, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

import { UnifiedPersonService } from '../../data-access/services/unified-person.service';
import { UnifiedPersonPayload, RequestingUnitOption } from '../../data-access/models/unified-person.model';

import { HttpErrorResponse } from '@angular/common/http';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-unified-person-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './unified-person-modal.component.html',
  styleUrls: ['./unified-person-modal.component.scss']
})
export class UnifiedPersonModalComponent implements OnInit {
  private fb = inject(FormBuilder);
  private personService = inject(UnifiedPersonService);
  private notificationService = inject(NotificationService);

  // Emisor para notificar al Dashboard que debe cerrar el modal
  @Output() modalClosed = new EventEmitter<void>();

  // Nuevo Input para recibir el ID cuando estamos en modo "Actualizar"
  @Input() personId: string | null = null; 

  public personForm!: FormGroup;
  
  // Signals de estado
  public isFunctional = signal<boolean>(false);
  public isCspe = signal<boolean>(false);
  public hasSystemAccess = signal<boolean>(false);
  public isEditMode = signal<boolean>(false); // Flag para la UI

  public requestingUnits = signal<RequestingUnitOption[]>([]);
  public systemRoles = ['Admin', 'Coord', 'ConsCSPE', 'Gerente', 'Viewer'];

  public readonly roleMasks: Record<string, string> = {
  'Admin': 'ADMINISTRADOR',
  'Coord': 'COORDINADOR-CSPE',
  'ConsCSPE': 'CONSULTOR-CSPE',
  'Gerente': 'GERENTE',
  'Viewer': 'AUDITOR'
};

  constructor() {
    effect(() => {
      // RN-Regla Aprovisionamiento Condicional: Si es CSPE, fuerza el acceso al sistema
      if (this.isCspe()) {
        this.hasSystemAccess.set(true);
        this.personForm.get('has_system_access')?.setValue(true, { emitEvent: false });
        this.updateDynamicValidators();
      }
    }, { allowSignalWrites: true });
  }

  ngOnInit(): void {
    this.initForm();
    this.listenToToggles();
    this.listenToEmailChanges(); 
    this.loadRequestingUnits();

    // Verificamos si recibimos un ID para entrar en modo edición
    if (this.personId) {
      this.isEditMode.set(true);
      this.loadPersonData(this.personId);
    }
  }

  private loadRequestingUnits(): void {
    this.personService.getRequestingUnits().subscribe({
      next: (units: RequestingUnitOption[]) => {
        this.requestingUnits.set(units);
      },
      error: (err: unknown) => {
        console.error('Error al cargar unidades solicitantes:', err);
        this.requestingUnits.set([]);
      }
    });
  }

  private initForm(): void {
    this.personForm = this.fb.group({
      first_name: ['', [Validators.required, Validators.maxLength(255)]],
      last_name: ['', [Validators.required, Validators.maxLength(255)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
      phone: ['', [Validators.maxLength(255)]],
      is_functional: [false],
      is_cspe: [false],
      has_system_access: [false],
      requesting_unit_id: [null],
      name: [''], 
      password: [''],
      roles: [[]]
    });
  }

  // 🟢 NUEVO MÉTODO: Lógica para generar el Username automáticamente
  private listenToEmailChanges(): void {
    this.personForm.get('email')?.valueChanges.subscribe((correo: string) => {
      if (correo) {
        // Extraemos todo lo que está antes del '@'
        const usernameGenerado = correo.split('@')[0];
        // Seteamos el valor sin emitir eventos extra para no hacer bucles
        this.personForm.get('name')?.setValue(usernameGenerado, { emitEvent: false });
      } else {
        this.personForm.get('name')?.setValue('', { emitEvent: false });
      }
    });
  }

  /**
   * Carga los datos de la Ficha Unificada desde el backend y los mapea al formulario.
   */
  private loadPersonData(id: string): void {
    this.personService.getIdentityById(id).subscribe({
      next: (res) => {
        const person = res.data;
        
        // Seteamos silenciosamente los Signals primero para evitar parpadeos
        this.isFunctional.set(person.is_functional);
        this.isCspe.set(person.is_cspe);
        this.hasSystemAccess.set(person.has_system_access);

        // Mapeamos los datos base y aplanamos los perfiles (profiles) enviados por el API Resource
        this.personForm.patchValue({
          first_name: person.first_name,
          last_name: person.last_name,
          email: person.email,
          phone: person.phone,
          is_functional: person.is_functional,
          is_cspe: person.is_cspe,
          has_system_access: person.has_system_access,
          requesting_unit_id: person.profiles?.functional?.requesting_unit_id || null,
          name: person.profiles?.user?.username || '',
          roles: person.profiles?.user?.roles || []
          // La contraseña no se precarga por razones de seguridad
        });

        this.updateDynamicValidators();
      },
      error: () => {
        this.notificationService.showError('Error', 'No se pudo cargar la información de la persona.');
        this.onClose();
      }
    });
  }

  private listenToToggles(): void {
    this.personForm.get('is_functional')?.valueChanges.subscribe(val => {
      this.isFunctional.set(val);
      this.updateDynamicValidators();
    });

    this.personForm.get('is_cspe')?.valueChanges.subscribe(val => {
      this.isCspe.set(val);
    });

    this.personForm.get('has_system_access')?.valueChanges.subscribe(val => {
      if (this.isCspe() && !val) {
        this.personForm.get('has_system_access')?.setValue(true, { emitEvent: false });
        return;
      }
      this.hasSystemAccess.set(val);
      this.updateDynamicValidators();
    });
  }

  private updateDynamicValidators(): void {
    const unitControl = this.personForm.get('requesting_unit_id');
    if (this.isFunctional()) {
      unitControl?.setValidators([Validators.required]);
    } else {
      unitControl?.clearValidators();
      unitControl?.setValue(null);
    }
    unitControl?.updateValueAndValidity();

    const nameControl = this.personForm.get('name');
    const passControl = this.personForm.get('password');
    const rolesControl = this.personForm.get('roles');

    if (this.hasSystemAccess()) {
      nameControl?.setValidators([Validators.required, Validators.maxLength(255)]);
      // En modo edición, la contraseña es opcional (solo se requiere si se va a cambiar)
      if (this.isEditMode()) {
        passControl?.setValidators([Validators.minLength(8)]);
      } else {
        passControl?.setValidators([Validators.required, Validators.minLength(8)]);
      }
      rolesControl?.setValidators([Validators.required]);
    } else {
      nameControl?.clearValidators();
      passControl?.clearValidators();
      rolesControl?.clearValidators();
      nameControl?.setValue('');
      passControl?.setValue('');
      rolesControl?.setValue([]);
    }

    nameControl?.updateValueAndValidity();
    passControl?.updateValueAndValidity();
    rolesControl?.updateValueAndValidity();
  }

  public onClose(): void {
    this.modalClosed.emit();
  }

  public onSubmit(): void {
    if (this.personForm.invalid) {
      this.personForm.markAllAsTouched();
      this.notificationService.showWarning(
        'Formulario Incompleto', 
        'Por favor, revise los campos marcados en rojo.'
      );
      return;
    }

    // Extraemos los valores crudos para garantizar la captura de inputs deshabilitados (si los hubiese)
    const payload: UnifiedPersonPayload = this.personForm.getRawValue();

    // Limpieza de payload: En modo edición, si la contraseña está vacía, la eliminamos para no sobreescribirla
    if (this.isEditMode() && !payload.password) {
      delete payload.password;
    }
    
    // Determinamos si invocamos creación o actualización
    const requestObservable = this.isEditMode() && this.personId
      ? this.personService.updateUnifiedPerson(this.personId, payload)
      : this.personService.createUnifiedPerson(payload);

    requestObservable.subscribe({
      next: (res) => {
        this.notificationService.showSuccess('¡Éxito!', res.message || 'Operación completada con éxito.');
        this.personForm.reset();
        this.onClose();
      },
      error: (err: HttpErrorResponse) => {
        if (err.status === 422 && err.error && err.error.errors) {
          const validationErrors = err.error.errors;

          Object.keys(validationErrors).forEach(field => {
            const formControl = this.personForm.get(field);
            if (formControl) {
              formControl.setErrors({ serverError: validationErrors[field][0] });
              formControl.markAsTouched(); 
            }
          });

          this.notificationService.showWarning(
            'Validación fallida', 
            'Se encontraron conflictos con la información ingresada. Revise los campos resaltados.'
          );
        } else {
          const errorMsg = err.error?.message || 'Ocurrió un error interno al procesar la identidad.';
          this.notificationService.showError('Error del Servidor', errorMsg);
        }
      }
    });
  }
}