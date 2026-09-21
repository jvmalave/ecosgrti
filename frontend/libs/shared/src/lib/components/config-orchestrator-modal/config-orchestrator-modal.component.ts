import { Component, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'lib-config-orchestrator-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './config-orchestrator-modal.component.html',
  styleUrls: ['./config-orchestrator-modal.component.scss'],
})
export class ConfigOrchestratorModalComponent {
  isVisible = input<boolean>(false);
  
  closeModalEvent = output<void>();
  triggerAction = output<string>();

  closeModal(): void {
    this.closeModalEvent.emit();
  }

  selectConfig(actionName: string): void {
    this.closeModal();
    // Retardo sutil para que el modal se cierre antes de abrir el siguiente
    setTimeout(() => {
      this.triggerAction.emit(actionName);
    }, 150);
  }
}