/**
 * Define los códigos de las fases soportadas por el motor polimórfico.
 */

export type PhaseCode = 'DT' | 'COR' | 'COE' | 'PI' | 'CER' | 'CEE' | 'PAP' | 'AU';

/**
 * Contrato que dicta el comportamiento, textos dinámicos y rutas API de la fase.
 */
export interface PhaseConfig {
  phaseCode: PhaseCode;
  phaseName: string;
  apiEndpoint: string;
  modalTitle: string;
  emptyStateText: string;
  // Propiedades para enrutamiento dinámico en el servicio
  initEndpoint: string;
  parentEntityPath: string;
  childEntityPath: string;
}

/**
 * Diccionario inmutable con las configuraciones predefinidas para cada fase.
 */
export const PHASE_CONFIGURATIONS: Record<PhaseCode, PhaseConfig> = {
  DT: {
    phaseCode: 'DT',
    phaseName: 'Diseño Técnico',
    apiEndpoint: 'dt',
    modalTitle: 'Roles de Diseño Técnico',
    emptyStateText: 'No hay documentos técnicos registrados para este rol.',
    initEndpoint: 'roles-init',
    parentEntityPath: 'roles',
    childEntityPath: 'registers'
  },
  COR: {
    phaseCode: 'COR',
    phaseName: 'Construcción - Roles',
    apiEndpoint: 'cor',
    modalTitle: 'Roles en la Fase Construcción',
    emptyStateText: 'No hay actividades registradas en la bitácora de este rol.',
    initEndpoint: 'roles-init',
    parentEntityPath: 'roles',
    childEntityPath: 'registers'
  },
  COE: {
    phaseCode: 'COE',
    phaseName: 'Construcción - Entregables',
    apiEndpoint: 'coe',
    modalTitle: 'Entregables en la Fase Construcción',
    emptyStateText: 'No hay registros de actividades para este entregable.',
    initEndpoint: 'deliverables-init',
    parentEntityPath: 'deliverables',
    childEntityPath: 'activities'
  },
  PI: {
    phaseCode: 'PI',
    phaseName: 'Pruebas Integrales',
    apiEndpoint: 'pi',
    modalTitle: 'Roles en Pruebas Integrales',
    emptyStateText: 'No hay hallazgos registrados en la bitácora de pruebas.',
    initEndpoint: 'roles-init',
    parentEntityPath: 'roles',
    childEntityPath: 'registers'
  },
  CER: {
    phaseCode: 'CER',
    phaseName: 'Certificación de Roles',
    apiEndpoint: 'cer',
    modalTitle: 'Certificación Técnica (CSAL)',
    emptyStateText: 'No hay observaciones de certificación.',
    initEndpoint: 'roles-init',
    parentEntityPath: 'roles',
    childEntityPath: 'results' 
  },
  CEE: {
    phaseCode: 'CEE',
    phaseName: 'Certificación de Entregables',
    apiEndpoint: 'cee',
    modalTitle: 'Certificación Documental',
    emptyStateText: 'No hay observaciones de certificación documental.',
    initEndpoint: 'deliverables-init',
    parentEntityPath: 'deliverables',
    childEntityPath: 'results' 
  },
  PAP: {
    phaseCode: 'PAP',
    phaseName: 'Pase a Producción',
    apiEndpoint: 'pap',
    modalTitle: 'Roles en Pase a Producción',
    emptyStateText: 'No hay órdenes de transporte registradas para este rol.',
    initEndpoint: 'roles-init',
    parentEntityPath: 'roles',
    childEntityPath: 'orders' 
  },
  AU: {
    phaseCode: 'AU',
    phaseName: 'Asignación de Usuarios',
    apiEndpoint: 'au',
    modalTitle: 'Asignación de Usuarios',
    emptyStateText: 'No hay planillas de acceso registradas.',
    initEndpoint: 'roles-init',
    parentEntityPath: 'roles',
    childEntityPath: 'accesses'
  }
};