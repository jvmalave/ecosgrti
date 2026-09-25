import { Component, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';

// Definimos la estructura exacta que deberá tener cada columna del modal
export interface StatusColumn {
  title: string;
  icon: string;         // Ej: 'fa-solid fa-users'
  bgClass: string;      // Clase de fondo de la cabecera (Ej: 'bg-warning-subtle')
  textClass: string;    // Clase de texto para el título y el icono
  items: Array<{ id: string; name: string; }>; // Arreglo genérico para Roles o Entregables
  emptyMessage: string; // Mensaje cuando no hay registros (Ej: 'No hay roles pendientes')
  emptyIcon: string;    //  Icono gigante para cuando está vacío
  itemIcon: string;     //  Icono pequeño para cada item
}

@Component({
  selector: 'lib-global-status-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './global-status-modal.component.html',
  styleUrls: ['./global-status-modal.component.scss']
})
export class GlobalStatusModalComponent {
  
  // ==========================================
  // INPUTS: Datos inyectados desde la Fase Padre
  // ==========================================
  
  // Título principal del modal (Ej: 'Estatus Global de Roles' o 'Estatus de Entregables')
  public modalTitle = input<string>('Estatus Global');
  
  // El número de requerimiento para el badge superior
  public requirementId = input<string>('');
  
  // La configuración de las 3 columnas con sus respectivos datos
  public columns = input.required<StatusColumn[]>();

  // ==========================================
  // OUTPUTS: Eventos hacia la Fase Padre
  // ==========================================
  
  // Evento para cerrar el modal desde el botón o la 'X'
  public closeModal = output<void>();

}