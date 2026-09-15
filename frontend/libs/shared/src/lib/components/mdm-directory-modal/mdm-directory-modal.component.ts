import { Component, effect, inject, signal, computed, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReportService } from '../../data-access/services/report.service';
import { NotificationService } from '@ecosgrti/workflow';

@Component({
  selector: 'lib-mdm-directory-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './mdm-directory-modal.component.html'
})
export class MdmDirectoryModalComponent {
  private reportService = inject(ReportService);
  private notificationService = inject(NotificationService);

  isVisible = input<boolean>(false);
  closeModalEvent = output<void>();

  directoryData = signal<any[]>([]);
  isLoading = signal<boolean>(true);
  isGeneratingPdf = signal<boolean>(false);

  // 1. Estado reactivo para el filtro
  selectedRole = signal<string>('');

  // 2. Diccionario de traducción de roles
  readonly roleMap: Record<string, string> = {
    'admin': 'Administrador',
    'Coord': 'Coordinador CSPE',
    'ConsCSPE': 'Consultor CSPE',
    'Gerente': 'Gerente',
    'Viewer': 'Lector',
  };

  // 3. Computed signal para filtrar la data en memoria
  filteredData = computed(() => {
    const roleFilter = this.selectedRole();
    const data = this.directoryData();
    
    if (!roleFilter) return data;
    return data.filter(user => user.roles && user.roles[0] === roleFilter);
  });

  constructor() {
    effect(() => {
      if (this.isVisible()) {
        this.selectedRole.set(''); // Resetea el filtro al abrir el modal
        this.loadData();
      }
    }, { allowSignalWrites: true });
  }

  closeModal(): void {
    this.closeModalEvent.emit();
  }

  onRoleChange(event: Event): void {
    const selectElement = event.target as HTMLSelectElement;
    this.selectedRole.set(selectElement.value);
  }

  translateRole(roleCode: string): string {
    if (!roleCode) return 'Sin Rol';
    return this.roleMap[roleCode] || roleCode;
  }

  private loadData(): void {
    this.isLoading.set(true);
    this.reportService.getMdmDirectoryData().subscribe({
      next: (res) => {
        if (res.success) {
          this.directoryData.set(res.data);
        }
        this.isLoading.set(false);
      },
      error: () => {
        // Uso del servicio centralizado de notificaciones
        this.notificationService.showError('Error de Carga', 'No se pudo cargar el directorio MDM.');
        this.isLoading.set(false);
      }
    });
  }

  downloadPdf(): void {
    this.isGeneratingPdf.set(true);
    this.notificationService.showLoading('Generando Directorio', 'Renderizando documento PDF...');

    // Capturamos el filtro activo en pantalla
    const currentRoleFilter = this.selectedRole();

    // Pasamos el filtro al servicio
    this.reportService.downloadMdmDirectoryReport(currentRoleFilter).subscribe({
      next: (blob: Blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Directorio_MDM_ECOSGRTI_${new Date().getTime()}.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
        link.remove();
        
        this.isGeneratingPdf.set(false);
        this.notificationService.close(); 
        this.notificationService.showSuccess('¡Éxito!', 'Directorio descargado correctamente.');
      },
      error: () => {
        this.isGeneratingPdf.set(false);
        this.notificationService.close();
        this.notificationService.showError('Error de Generación', 'Fallo al procesar el documento PDF.');
      }
    });
  }
}