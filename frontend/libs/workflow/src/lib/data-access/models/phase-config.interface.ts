

/**
 * Define los códigos de las fases soportadas por el motor polimórfico.
 */
export type PhaseCode = 'DT' | 'COR' | 'COE';

/**
 * Contrato que dicta el comportamiento y los textos dinámicos de los componentes de la interfaz.
 */
export interface PhaseConfig {
  phaseCode: PhaseCode;
  phaseName: string;
  apiEndpoint: string;
  modalTitle: string;
  emptyStateText: string;
}

/**
 * Diccionario inmutable con las configuraciones predefinidas para cada fase.
 * Esto evita tener textos quemados esparcidos por múltiples componentes.
 */
export const PHASE_CONFIGURATIONS: Record<PhaseCode, PhaseConfig> = {
  DT: {
    phaseCode: 'DT',
    phaseName: 'Diseño Técnico',
    apiEndpoint: 'dt',
    modalTitle: 'Roles de Diseño Técnico',
    emptyStateText: 'No hay documentos técnicos registrados para este rol.',
  },
  COR: {
    phaseCode: 'COR',
    phaseName: 'Construcción - Roles',
    apiEndpoint: 'cor',
    modalTitle: 'Bitácora de Construcción',
    emptyStateText: 'No hay actividades registradas en la bitácora de este rol.',
  },
  COE: {
    phaseCode: 'COE',
    phaseName: 'Construcción - Entregables',
    apiEndpoint: 'coe',
    modalTitle: 'Gestión de Entregables',
    emptyStateText: 'No hay entregables registrados en esta fase.',
  }
};