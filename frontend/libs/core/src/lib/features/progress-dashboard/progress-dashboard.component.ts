// Ruta recomendada: frontend/libs/core/src/lib/feature/progress-dashboard/progress-dashboard.component.ts

import { Component, input, computed } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'lib-progress-dashboard', 
  standalone: true,
  imports: [CommonModule],
  templateUrl: './progress-dashboard.component.html',
  styleUrls: ['./progress-dashboard.component.scss']
})
export class ProgressDashboardComponent {
  
  // 1. Entradas (Inputs) reactivas usando Signals para el avance global
  public globalProgress = input.required<number>();
  public globalStatus = input<string>('Requerimiento Registrado');

  // 2. Lógica del semáforo visual (Computed Signals)
  
  /**
   * Calcula el color de la barra en base al porcentaje global.
   * Utiliza las clases utilitarias nativas de Bootstrap 5.
   */
  public barColorClass = computed(() => {
    const p = this.globalProgress();

    // Verde: Requerimiento completado (Fase Cierre - 100%)
    if (p === 100) return 'bg-success';
    
    // Azul: Trabajo en progreso en cualquier fase técnica (1% - 99%)
    if (p > 0 && p < 100) return 'bg-primary';
    
    // Gris: Sin iniciar (0%)
    return 'bg-secondary';
  });

  /**
   * Sincroniza el color del texto del porcentaje con el de la barra.
   */
  public textClass = computed(() => {
    const p = this.globalProgress();
    
    if (p === 100) return 'text-success';
    if (p > 0) return 'text-primary';
    return 'text-secondary';
  });
}