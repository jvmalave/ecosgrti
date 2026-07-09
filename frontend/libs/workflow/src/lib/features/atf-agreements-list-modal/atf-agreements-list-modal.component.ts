// Ruta: frontend/libs/workflow/src/lib/ui-atf-agreements-list-modal/atf-agreements-list-modal.component.ts

import { Component, inject, input, output, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { WorkflowApiService } from '../../data-access/services/workflow-api.service';
import { AtfAgreementDetail } from '../../data-access/models/atf-agreement.model';
import Swal from 'sweetalert2';
import { UpdateManagementTypeModalComponent } from '../update-management-type-modal/update-management-type-modal.component';

@Component({
  selector: 'lib-atf-agreements-list-modal',
  standalone: true,
  imports: [CommonModule, UpdateManagementTypeModalComponent],
  templateUrl: './atf-agreements-list-modal.component.html',
  styleUrl: './atf-agreements-list-modal.component.scss'
})
export class AtfAgreementsListModalComponent implements OnInit {
  private workflowApi = inject(WorkflowApiService);
  
  public requirementId = input.required<string>();
  public isAtfOpen = input<boolean>(true);
  public closeModal = output<void>();
  public openCreateModal = output<void>();
  public currentManagementType = input.required<string>();

  public agreements = signal<AtfAgreementDetail[]>([]);
  public isLoading = signal<boolean>(true);
  public viewAgreementDetail = output<AtfAgreementDetail>();
  public isUpdateTypeModalOpen = signal<boolean>(false);

  public typeUpdated = output<{ tipo_gestion: string, progreso_global: number }>();
  

  
  ngOnInit(): void {
    this.loadAgreements();
  }

  public loadAgreements(): void {
    this.isLoading.set(true);
    this.workflowApi.getAgreements(this.requirementId()).subscribe({
      next: (res) => {
        this.agreements.set(res.data);
        this.isLoading.set(false);
      },
      error: () => this.isLoading.set(false)
    });
  }

  public viewAgreement(agreement: AtfAgreementDetail): void {

    this.viewAgreementDetail.emit(agreement);
  }

  public deleteAgreement(agreementId: string): void {
    Swal.fire({
      title: '¿Estas seguro de eliminar acuerdo?',
      text: "Esta acción removerá el acuerdo de la lista ",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33', // Rojo peligro
      cancelButtonColor: '#6c757d', // Gris secundario
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      
      if (result.isConfirmed) {
        // Ejecutamos la petición HTTP
        this.workflowApi.deleteAgreement(this.requirementId(), agreementId).subscribe({
          next: () => {
            // Mostramos alerta de éxito (Toast o Modal pequeño)
            Swal.fire(
              '¡Eliminado!',
              'El acuerdo técnico ha sido removido.',
              'success'
            );
            
            // Refrescamos la vista local o disparamos el evento para que el Dashboard recargue
            // Si tienes un array local signal llamado 'agreements', puedes filtrarlo directamente:
            this.agreements.update(items => items.filter(item => item.id !== agreementId));
            
            // O emitir una señal global de actualización:
            this.workflowApi.refreshDashboard$.next();
          },
          error: (err) => {
            console.error('Error al eliminar:', err);
            Swal.fire(
              'Error',
              'No se pudo eliminar el acuerdo. Intente de nuevo.',
              'error'
            );
          }
        });
      }
      
    });
  }

  // Método para manejar la actualización exitosa
  public onTypeUpdated(event: { tipo_gestion: string, progreso_global: number }): void {
    this.isUpdateTypeModalOpen.set(false);
    
    // 🚀 Pasamos el dato hacia arriba (Al Dashboard)
    this.typeUpdated.emit(event); 
  }
}