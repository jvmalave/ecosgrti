// Ruta: libs/workflow/src/lib/data-access/models/workflow-state.interface.ts

/**
 * Contrato de tipado estricto para el manejo del estado transaccional
 * e inmutabilidad de la fase ATF (Evidencia: CU-017.5 y CU-026).
 */
export interface WorkflowRequirementState {
  id: string;                                    // UUID v4
  rrti: string;                                  // Nomenclatura institucional (Ej: REQ-2026-001)
  is_locked: boolean;                            // Bandera de inmutabilidad física de la BD
  status: string;                                // Estado/Fase actual (Ej: 'PL', 'ATF', 'CLOSED')
  management_type: 'roles' | 'entregables' | 'mixto' | null; // Nombre exacto de la migración
  progress_percentage: number;                   // Decimal (5,2) de la BD
  has_atf_agreements: boolean;                   // Flag inyectado por el backend (COUNT > 0)
}