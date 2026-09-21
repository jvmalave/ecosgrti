import { Component, input, output, computed } from '@angular/core';
import { CommonModule } from '@angular/common';


export interface AlertGroup {
  rrti: string;
  maxDelay: number;
  details: any[];
}

@Component({
  selector: 'lib-alerts-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './alerts-modal.component.html',
  styles: [`
    .rrti-card {
      border-left: 5px solid #dc3545;
      transition: box-shadow 0.2s ease;
    }
    .rrti-card:hover {
      box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15)!important;
    }
    .phase-icon-box {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      font-size: 1.2rem;
    }
  `]
})
export class AlertsModalComponent {
  isVisible = input<boolean>(false);
  alerts = input<any[]>([]); // Recibe el arreglo plano de alertas
  closeModalEvent = output<void>();

  // Agrupa las alertas por RRTI calculando el atraso máximo del caso
  groupedAlerts = computed<AlertGroup[]>(() => {
  // Tipamos explícitamente el diccionario agrupador
  const groups: Record<string, AlertGroup> = {};
  
  this.alerts().forEach(alert => {
    if (!groups[alert.rrti]) {
      groups[alert.rrti] = { 
        rrti: alert.rrti, 
        maxDelay: 0, 
        details: [] 
      };
    }
    groups[alert.rrti].details.push(alert);
    
    // Guarda el peor atraso para ordenar luego
    if (alert.days_late > groups[alert.rrti].maxDelay) {
      groups[alert.rrti].maxDelay = alert.days_late;
    }
  });

  // El template ahora sabrá exactamente qué propiedades tiene este arreglo
  return Object.values(groups).sort((a, b) => b.maxDelay - a.maxDelay);
});

  closeModal(): void {
    this.closeModalEvent.emit();
  }

  // Asigna un ícono y color semántico dependiendo de la fase afectada
  getPhaseVisuals(phaseName: string): { icon: string, bg: string, text: string } {
    const p = phaseName.toUpperCase();
    if (p.includes('DISE')) return { icon: 'fa-laptop-code', bg: 'bg-primary', text: 'text-primary' };
    if (p.includes('CONST')) return { icon: 'fa-cogs', bg: 'bg-brand', text: 'text-brand' }; // Usa tu variable brand fucsia
    if (p.includes('PRUEB')) return { icon: 'fa-vial', bg: 'bg-warning', text: 'text-warning' };
    if (p.includes('CERT')) return { icon: 'fa-user-check', bg: 'bg-info', text: 'text-info' };
    if (p.includes('IMPLEM')) return { icon: 'fa-server', bg: 'bg-success', text: 'text-success' };
    return { icon: 'fa-layer-group', bg: 'bg-secondary', text: 'text-secondary' };
  }
}