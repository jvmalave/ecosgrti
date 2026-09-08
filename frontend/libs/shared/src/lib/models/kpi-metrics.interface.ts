// Filtros opcionales para las peticiones
export interface KpiFilters {
  start_date?: string;
  end_date?: string;
}

// Interfaces para OTD
export interface OtdResponse {
  success: boolean;
  data: {
    otd_percentage: number;
    on_time_count: number;
    late_count: number;
    total_evaluated: number;
  };
}

// Interfaces para Desviación de Cronograma
export interface PhaseBreakdown {
  total_evaluated: number;
  on_time_count: number;
  delayed_count: number;
  average_delay: number;
}

export interface ActiveAlert {
  rrti: string;
  phase: string;
  type: 'START_DELAY' | 'END_DELAY';
  message: string;
  days_late: number;
}

export interface DeviationResponse {
  success: boolean;
  data: {
    deviation_stats: {
      global_average_delay_days: number;
      total_phases_evaluated: number;
      phases_on_time: number;
      phases_delayed: number;
      breakdown_by_phase: Record<string, PhaseBreakdown>;
    };
    active_alerts: ActiveAlert[];
  };
}

// Interfaces para Envejecimiento (Aging)
export interface CriticalRequirement {
  rrti: string;
  global_days_open: number;
  current_phase: string;
  days_in_current_phase: number;
  is_stuck: boolean;
}

export interface AgingResponse {
  success: boolean;
  data: {
    summary: {
      total_open_requirements: number;
      average_global_age: number;
      average_phase_age: number;
    };
    aging_buckets: {
      '0_15_days': number;
      '16_30_days': number;
      '31_60_days': number;
      'over_60_days': number;
    };
    critical_requirements: CriticalRequirement[];
  };
}