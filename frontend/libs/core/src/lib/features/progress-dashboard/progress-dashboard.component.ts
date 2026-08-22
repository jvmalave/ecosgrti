import { Component, input, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Requirement } from '../../data-access/models/requirement.model';
import { RequirementMapModalComponent } from '../requirement-map-modal/requirement-map-modal.component'; 

@Component({
  selector: 'lib-progress-dashboard', 
  standalone: true,
  imports: [CommonModule, RequirementMapModalComponent],
  templateUrl: './progress-dashboard.component.html',
  styleUrls: ['./progress-dashboard.component.scss']
})
export class ProgressDashboardComponent {
  
  // 1. Entradas (Inputs) reactivas usando Signals para el avance global
  public globalProgress = input.required<number>();
  public globalStatus = input<string>('PL');
  readonly requirement = input<Requirement | null>(null);
  
  // 🟢 ESTADO REACTIVO PARA EL MODAL DEL MAPA
  public showMapModal = signal<boolean>(false);

  // 🟢 MÉTODO PARA ABRIR EL MAPA
  public openMap(): void {
    this.showMapModal.set(true);
  }

  // Definición del diccionario de datos centralizado
  public readonly statusDictionary: Record<string, string> = {
    'RC': 'Requerimiento Creado',
    'ES-R': 'Estimación Creada',
    'ATF-I': 'Acuerdos en Proceso',
    'ATF-C': 'Acuerdos Cerrados',
    'DT-I':  'Diseño Técnico en Proceso',
    'DT-C':  'Diseño Técnico Cerrado',
    'COR-I': 'Construcción (Roles) en Proceso',
    'COR-C': 'Construcción (Roles) Cerrado',
    'COE-I': 'Construcción (Entregables) en Proceso',
    'COE-C': 'Construcción (Entregables) Cerrado',
    'CEE-I': 'Certificación (Entregables) en Proceso',
    'CEE-C': 'Certificación (Entregables) Cerrado',
    'CER-I': 'Certificación (Roles) en Proceso',
    'CER-C': 'Certificación (Roles) Cerrado',
    'PI-I':  'Pruebas Integrales en Proceso',
    'PI-C':  'Pruebas Integrales Cerradas',
    'PAP-I': 'Pase a Producción en Proceso',
    'PAP-C': 'Pase a Producción Cerrado',
    'AU': 'Asignado a Usuario Cerrado',
    'RF': 'Requerimiento Cerrado',
  };

  // Getter para resolver el nombre del estado dinámicamente en la vista
  get currentStatusName(): string {
    const statusCode = this.globalStatus(); 
    return this.statusDictionary[statusCode] || 'Estado Desconocido';
  }

  // 2. Lógica del semáforo visual (Computed Signals)
  
  public barColorClass = computed(() => {
    const p = this.globalProgress();
    if (p === 100) return 'bg-success';
    if (p > 0 && p < 100) return 'bg-primary';
    return 'bg-secondary';
  });

  public textClass = computed(() => {
    const p = this.globalProgress();
    if (p === 100) return 'text-success';
    if (p > 0) return 'text-primary';
    return 'text-secondary';
  });
}