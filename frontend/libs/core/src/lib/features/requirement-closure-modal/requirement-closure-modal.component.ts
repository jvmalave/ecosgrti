import { Component, EventEmitter, Input, OnInit, Output, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { RequirementClosureService } from '../../data-access/services/requirement-closure.service';
import { RequirementDashboard } from '../../data-access/models/requirement.model';



@Component({
  selector: 'lib-requirement-closure-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './requirement-closure-modal.component.html',
  styleUrls: ['./requirement-closure-modal.component.scss']
})
export class RequirementClosureModalComponent implements OnInit {

  // ==========================================
  // INYECCIÓN DE DEPENDENCIAS
  // ==========================================
  private fb = inject(FormBuilder);
  private closureService = inject(RequirementClosureService);
  private sanitizer = inject(DomSanitizer);

  // ==========================================
  // ENTRADAS Y SALIDAS
  // ==========================================
  @Input({ required: true }) req!: RequirementDashboard;
  @Output() closeModal = new EventEmitter<void>();
  @Output() closureSuccess = new EventEmitter<string>(); // Emite el ID del requerimiento cerrado

  // ==========================================
  // ESTADO Y FORMULARIO
  // ==========================================
  public closureForm!: FormGroup;
  public selectedFile = signal<File | null>(null);
  
  // Control de interfaz (UI States)
  public isLoading = signal<boolean>(false);
  public isDraftGenerated = signal<boolean>(false); // Cambia entre Fase 1 (Form) y Fase 2 (PDF)
  public draftPdfUrl = signal<SafeResourceUrl | null>(null);
  public errorMessage = signal<string | null>(null);

  ngOnInit(): void {
    // Inicialización del formulario reactivo
    this.closureForm = this.fb.group({
      notification_date: ['', Validators.required],
      completion_date: ['', Validators.required],
      conformity_declaration: [false, Validators.requiredTrue] // Obliga a que el checkbox sea true
    });
  }

  // ==========================================
  // MÉTODOS DE LA VISTA
  // ==========================================

  /**
   * Captura el archivo seleccionado y valida su tipo/tamaño.
   */
  public onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      
      // Validación Frontend básica (5MB)
      if (file.type !== 'application/pdf') {
        this.errorMessage.set('Solo se permiten archivos PDF.');
        this.selectedFile.set(null);
        input.value = ''; // Limpia el input
        return;
      }
      
      if (file.size > 5 * 1024 * 1024) {
        this.errorMessage.set('El archivo no debe superar los 5MB.');
        this.selectedFile.set(null);
        input.value = '';
        return;
      }

      this.errorMessage.set(null);
      this.selectedFile.set(file);
    }
  }

  /**
   * ETAPA 1: Envía los datos para generar el Acta Borrador
   */
  public generateDraft(): void {
    if (this.closureForm.invalid || !this.selectedFile()) {
      this.closureForm.markAllAsTouched();
      this.errorMessage.set('Por favor, complete todos los campos y adjunte el soporte PDF.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set(null);

    const formData = this.buildFormData();

    this.closureService.generateDraft(this.req.id, formData).subscribe({
      next: (response) => {
        // Convertimos el base64 que nos manda Laravel en un Blob URL seguro para el iframe
        const pdfBlob = this.base64ToBlob(response.draft_pdf, 'application/pdf');
        const objectUrl = URL.createObjectURL(pdfBlob);
        
        // Sanitizamos la URL para que Angular confíe en ella y la muestre en el iframe/object
        this.draftPdfUrl.set(this.sanitizer.bypassSecurityTrustResourceUrl(objectUrl));
        this.isDraftGenerated.set(true); // Pasamos a la Etapa 2
        this.isLoading.set(false);
      },
      error: (err) => {
        this.errorMessage.set(err.error?.message || 'Error al generar el borrador. Verifique las fechas.');
        this.isLoading.set(false);
      }
    });
  }

  /**
   * ETAPA 2: Transacción Atómica (Cierre Definitivo)
   */
  public confirmClosure(): void {
    this.isLoading.set(true);
    const formData = this.buildFormData();

    this.closureService.finalizeClosure(this.req.id, formData).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        // Disparamos el evento al Dashboard para que reaccione (RN-FR-08)
        this.closureSuccess.emit(this.req.id);
        console.log(response.message);
      },
      error: (err) => {
        this.errorMessage.set(err.error?.message || 'Error al ejecutar el cierre definitivo.');
        this.isLoading.set(false);
      }
    });
  }

  /**
   * Aborta el proceso en la Etapa 2 y vuelve al formulario.
   */
  public cancelDraft(): void {
    this.isDraftGenerated.set(false);
    this.draftPdfUrl.set(null);
  }


  public downloadAct(): void {
    // Aquí llamaremos al endpoint de Laravel que descarga el acta del disco 'private'
    console.log('Descargando Acta desde:', this.req.closure_act_path);
  }

  public downloadSupport(): void {
    // Aquí llamaremos al endpoint de Laravel que descarga el soporte del disco 'private'
    console.log('Descargando Soporte desde:', this.req.notification_support_path);
  }

  // ==========================================
  // UTILERÍAS
  // ==========================================

  private buildFormData(): FormData {
    const data = new FormData();
    data.append('notification_date', this.closureForm.get('notification_date')?.value);
    data.append('completion_date', this.closureForm.get('completion_date')?.value);
    // IMPORTANTE: Laravel espera el checkbox como '1'/'0', 'true'/'false' o 'on'
    data.append('conformity_declaration', '1'); 
    
    if (this.selectedFile()) {
      data.append('notification_file', this.selectedFile() as Blob);
    }
    return data;
  }

  /**
   * Convierte un string Base64 a un objeto Blob para poder mostrarlo.
   */
  private base64ToBlob(base64: string, contentType= ''): Blob {
    const byteCharacters = atob(base64);
    const byteArrays = [];

    for (let offset = 0; offset < byteCharacters.length; offset += 512) {
      const slice = byteCharacters.slice(offset, offset + 512);
      const byteNumbers = new Array(slice.length);
      for (let i = 0; i < slice.length; i++) {
        byteNumbers[i] = slice.charCodeAt(i);
      }
      const byteArray = new Uint8Array(byteNumbers);
      byteArrays.push(byteArray);
    }
    return new Blob(byteArrays, { type: contentType });
  }
}