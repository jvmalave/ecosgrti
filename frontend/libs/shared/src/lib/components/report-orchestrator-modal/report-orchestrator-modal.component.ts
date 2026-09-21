import { Component, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'lib-report-orchestrator-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './report-orchestrator-modal.component.html',
  styleUrls: ['./report-orchestrator-modal.component.scss'],
})
export class ReportOrchestratorModalComponent {
  isVisible = input<boolean>(false);
  
  // Emisor para cerrar el modal
  closeModalEvent = output<void>();
  
  // Emisor para indicarle al Dashboard qué reporte abrir
  triggerAction = output<string>();

  closeModal(): void {
    this.closeModalEvent.emit();
  }

  selectReport(actionName: string): void {
    // 1. Cerramos este modal orquestador inmediatamente
    this.closeModal();
    
    // 2. Le damos un pequeño respiro al navegador (150ms) para que elimine el backdrop oscuro
    // antes de emitir la acción que podría abrir un SweetAlert u otro modal.
    setTimeout(() => {
      this.triggerAction.emit(actionName);
    }, 150);
  }
}