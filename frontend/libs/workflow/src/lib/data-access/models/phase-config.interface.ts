/**
 * Define los códigos de las fases soportadas por el motor polimórfico.
 */
export type PhaseCode = 'DT' | 'COR' | 'COE';

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
  }
};