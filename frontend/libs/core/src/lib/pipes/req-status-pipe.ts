import { Pipe, PipeTransform } from '@angular/core';

interface StatusConfig {
  label: string;
  cssClass: string;
}

@Pipe({
  name: 'reqStatus',
  standalone: true
})
export class ReqStatusPipe implements PipeTransform {

  // Diccionario centralizado con paleta de colores atenuada (Pastel/Subtle)
  private readonly statusDictionary: Record<string, StatusConfig> = {
    
    // ---------------------------------------------------------
    // PREPARACIÓN Y ESPERA (Grises tenues)
    // ---------------------------------------------------------
    'RC': { label: 'REQ Creado', cssClass: 'bg-secondary bg-opacity-10 text-secondary fw-medium' },
    'ES-R': { label: 'EST Registrada', cssClass: 'bg-secondary bg-opacity-10 text-secondary fw-medium' },
    
    // ---------------------------------------------------------
    // FASES EN EJECUCIÓN / INICIADAS (Azules tenues)
    // Usamos primary en lugar de info para mejorar el contraste del texto
    // ---------------------------------------------------------
    'ATF-I': { label: 'ATF Iniciado', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'DT-I': { label: 'DT Iniciado', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'COR-I': { label: 'COR Iniciado', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'COE-I': { label: 'COE Iniciado', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'PI-I': { label: 'PI Iniciadas', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'CER-I': { label: 'CER Iniciada', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'CEE-I': { label: 'CEE Iniciada', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'PAP-I': { label: 'PAP Iniciado', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    'AU-I': { label: 'AU Iniciado', cssClass: 'bg-primary bg-opacity-10 text-primary fw-bold' },
    
    // ---------------------------------------------------------
    // FASES COMPLETADAS / EXITOSAS (Verdes tenues)
    // ---------------------------------------------------------
    'ATF-C': { label: 'ATF Completado', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'DT-C': { label: 'DT Completado', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'COR-C': { label: 'COR Completado', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'COE-C': { label: 'COE Completado', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'PI-C': { label: 'PI Completadas', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'CER-C': { label: 'CER Completada', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'CEE-C': { label: 'CEE Completada', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'PAP-C': { label: 'PAP Completado', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    'AU-C': { label: 'AU Completado', cssClass: 'bg-success bg-opacity-10 text-success fw-bold' },
    
    // ---------------------------------------------------------
    // CIERRE ABSOLUTO (Oscuros tenues)
    // ---------------------------------------------------------
    'RF': { label: 'REQ Finalizado', cssClass: 'bg-danger bg-opacity-10 text-danger fw-bold' }
  };
  transform(value: string, returnType: 'label' | 'class' = 'label'): string {
    // Fallback de seguridad en caso de que llegue un estado no mapeado
    const config = this.statusDictionary[value] || { label: value, cssClass: 'bg-light text-dark border' };
    return returnType === 'label' ? config.label : config.cssClass;
  }
}